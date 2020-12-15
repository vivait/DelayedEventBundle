<?php

namespace Vivait\DelayedEventBundle\Command;

use Exception;
use InvalidArgumentException;
use Leezy\PheanstalkBundle\Proxy\PheanstalkProxy;
use Pheanstalk\Job;
use Pheanstalk\PheanstalkInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Throwable;
use Vivait\Backoff\Strategies\AbstractBackoffStrategy;
use Vivait\TenantBundle\Model\Tenant;
use Wrep\Daemonizable\Command\EndlessContainerAwareCommand;

use function is_numeric;

/**
 * Class JobDispatcherCommand
 * @package Vivait\DelayedEventBundle\Command
 */
class JobDispatcherCommand extends EndlessContainerAwareCommand
{
    /**
     * How long should we wait for a job before recycling.
     *
     * To be able to exit this command gracefully, this should always be
     * set to a value other than null via the input option on the command
     */
    public const DEFAULT_WAIT_TIMEOUT = 10;

    /**
     * Overrides the default timeout on how long to wait before creating the next worker
     *
     * @see EndlessCommand
     */
    public const DEFAULT_TIMEOUT = 0.1;

    /**
     * The prefix added to tenants to find their environment
     */
    public const TENANT_ENVIRONMENT_PREFIX = 'tenant_';

    /**
     * How many jobs we should process before this worker dispatcher restarts (and is then restarted by upstart)
     */
    public const JOBS_BEFORE_EXIT = 1000;

    /**
     * @var PheanstalkProxy
     */
    private $queue;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var AbstractBackoffStrategy
     */
    private $backoffStrategy;

    /**
     * @var int
     */
    private $jobsProcessed = 0;

    /**
     * @var float
     */
    private $processStartedTime = 0.0;

    public function __construct(
        PheanstalkProxy $queue,
        KernelInterface $kernel,
        LoggerInterface $logger,
        AbstractBackoffStrategy $backoffStrategy
    ) {
        $this->queue = $queue;
        $this->kernel = $kernel;
        $this->logger = $logger;
        $this->backoffStrategy = $backoffStrategy;

        parent::__construct('vivait:worker:run');

        // override the timeout on the endless command
        $this->setTimeout(self::DEFAULT_TIMEOUT);
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->setDescription('Waits for a job in an environments tubes and passes the job to a worker to execute.')
            ->addOption(
                'queue-timeout',
                null,
                InputOption::VALUE_OPTIONAL,
                'Maximum time to wait for a job (in seconds) before recycling the worker',
                self::DEFAULT_WAIT_TIMEOUT
            )
            ->addOption(
                'environmentList',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'CSV of environments to watch. If none are set, check all tenant environments.'
            )
            ->addOption(
                'jobs-before-exit',
                'j',
                InputOption::VALUE_REQUIRED,
                'The number of jobs to process before the command exits',
                self::JOBS_BEFORE_EXIT
            )
            ->addOption(
                'ignore-errors',
                'i',
                InputOption::VALUE_NONE,
                'Ignore errors and keep command alive'
            );
    }

    protected function starting(InputInterface $input, OutputInterface $output)
    {
        parent::starting($input, $output);

        $jobsBeforeExit = $input->getOption('jobs-before-exit');
        $this->processStartedTime = microtime(true);

        $this->logger->info(
            sprintf(
                'Worker started and will stop after %d jobs',
                $jobsBeforeExit
            ),
            [
                'jobsBeforeRecycle' => $jobsBeforeExit
            ]
        );
    }

    protected function finalize(InputInterface $input, OutputInterface $output)
    {
        $processDurationSeconds = (int)round(microtime(true) - $this->processStartedTime);

        $this->logger->info(
            sprintf(
                'Worker stopped after %d seconds and had processed %d jobs',
                $processDurationSeconds,
                $this->jobsProcessed
            ),
            [
                'processDurationSeconds' => $processDurationSeconds,
                'jobsProcessed' => $this->jobsProcessed
            ]
        );
        parent::finalize($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environmentList = $this->getEnvironmentList($input->getOption('environmentList'));
        $this->watchEnvironmentTubes($environmentList);

        if (
            is_numeric($input->getOption('queue-timeout'))
            && $input->getOption('queue-timeout') >= 0
        ) {
            $timeout = (int)round($input->getOption('queue-timeout'));
        } else {
            throw new InvalidArgumentException('--queue-timeout should be >= 0');
        }

        $job = $this->waitForJob($timeout);

        if ($job === null) {
            $this->logger->debug("Couldn't find job before timeout. Exiting process to be restarted");

            return;
        }

        $this->jobsProcessed++;

        $payload = $this->getPayload($job);
        $tenant = $this->getTenantFromTubeName($payload['tube']);
        $currentAttempt = $payload['currentAttempt'] ?? 1;
        $maxAttempts = $payload['maxAttempts'] ?? $payload['maxRetries'] ?? 1;
        $jobId = $payload['id'] ?? $job->getId() ?? 'N/A';
        $ttr = $payload['ttr'] ?? 60;

        $jobStartedTime = microtime(true);

        $this->logger->info(
            sprintf(
                'Job [%s] starting %s %s/%s',
                $jobId,
                $currentAttempt >= $maxAttempts ? 'final attempt' : 'attempt',
                $currentAttempt,
                $maxAttempts
            ),
            [
                'beanstalkId' => $job->getId(),
                'jobId' => $jobId,
                'environment' => $tenant,
                'tube' => $payload['tube'],
                'eventName' => $payload['eventName'],
                'currentAttempt' => $currentAttempt,
                'maxAttempts' => $maxAttempts,
                'ttr' => $ttr
            ]
        );

        try {
            $return = $this->runJobForTenant(
                $tenant,
                $payload['eventName'],
                $payload['event'],
                $ttr,
                $input->getOption('ignore-errors'),
                $jobId
            );

            /*
             * If the command hard fails, then just bury the job as it shouldn't be retried
             */
            if ($return === ProcessJobCommand::JOB_HARD_FAIL) {
                $this->queue->bury($job);

                $this->logger->error(
                    sprintf(
                        'Job [%s] has hard-failed and will be buried',
                        $jobId
                    ),
                    [
                        'beanstalkId' => $job->getId(),
                        'jobId' => $jobId,
                        'environment' => $tenant,
                        'tube' => $payload['tube'],
                        'eventName' => $payload['eventName'],
                        'exitCode' => $return,
                        'durationSeconds' => microtime(true) - $jobStartedTime,
                        'currentAttempt' => $currentAttempt,
                        'maxAttempts' => $maxAttempts,
                        'ttr' => $ttr
                    ]
                );

                $output->write('X');


                return;
            }

            /*
             * If the command soft fails but it is the last attempt, then just bury the job as it shouldn't be retried
             */
            if ($return === ProcessJobCommand::JOB_SOFT_FAIL && $currentAttempt >= $maxAttempts) {
                $this->queue->bury($job);

                $this->logger->error(
                    sprintf(
                        'Job [%s] has soft-failed but has reached the last attempt and will be buried',
                        $jobId
                    ),
                    [
                        'beanstalkId' => $job->getId(),
                        'jobId' => $jobId,
                        'environment' => $tenant,
                        'tube' => $payload['tube'],
                        'eventName' => $payload['eventName'],
                        'exitCode' => $return,
                        'durationSeconds' => microtime(true) - $jobStartedTime,
                        'currentAttempt' => $currentAttempt,
                        'maxAttempts' => $maxAttempts,
                        'ttr' => $ttr
                    ]
                );

                $output->write('X');


                return;
            }

            /*
             * If the return is a soft fail then re-add the job to be processed with a delay.
             */
            if ($return === ProcessJobCommand::JOB_SOFT_FAIL && $currentAttempt < $maxAttempts) {
                /*
                 * If we soft fail, we want to delete the job and then re-add it to the queue so it can be re-processed
                 * until the max retries are reached and the process hard fails (and is then buried)
                 */
                $this->queue->delete($job);

                $delay = $this->backoffStrategy->getDelay($currentAttempt);

                $this->queue->putInTube(
                    $payload['tube'],
                    json_encode(
                        [
                            'eventName' => $payload['eventName'],
                            'event' => $payload['event'],
                            'tube' => $payload['tube'],
                            'currentAttempt' => $currentAttempt + 1,
                            'maxAttempts' => $maxAttempts,
                            'ttr' => $payload['ttr'],
                            'id' => $jobId
                        ]
                    ),
                    PheanstalkInterface::DEFAULT_PRIORITY,
                    $delay,
                    $ttr
                );

                $this->logger->warning(
                    sprintf(
                        'Job [%s] has soft-failed and will be retried after %d seconds',
                        $jobId,
                        $delay
                    ),
                    [
                        'beanstalkId' => $job->getId(),
                        'jobId' => $jobId,
                        'environment' => $tenant,
                        'tube' => $payload['tube'],
                        'eventName' => $payload['eventName'],
                        'exitCode' => $return,
                        'durationSeconds' => microtime(true) - $jobStartedTime,
                        'currentAttempt' => $currentAttempt,
                        'maxAttempts' => $maxAttempts,
                        'delaySeconds' => $delay,
                        'ttr' => $ttr
                    ]
                );

                $output->write('x');

                return;
            }
        } catch (Throwable $e) {
            $this->logger->error(
                sprintf(
                    "Job [%s] has thrown an Exception and will be buried: {$e->getMessage()}, {$e->getTraceAsString()}",
                    $jobId
                ),
                [
                    'beanstalkId' => $job->getId(),
                    'jobId' => $jobId,
                    'environment' => $tenant,
                    'tube' => $payload['tube'],
                    'eventName' => $payload['eventName'],
                    'durationSeconds' => microtime(true) - $jobStartedTime,
                    'currentAttempt' => $currentAttempt,
                    'maxAttempts' => $maxAttempts,
                    'ttr' => $ttr
                ]
            );

            // If we catch any other exceptions that we can't handle,
            // bury the job as re-trying likely won't get us anywhere
            $this->queue->bury($job);

            $output->write('E');

            return;
        }

        // Successfully processed, delete the job
        $this->logger->info(
            sprintf(
                'Job [%s] has completed successfully',
                $jobId
            ),
            [
                'beanstalkId' => $job->getId(),
                'jobId' => $jobId,
                'environment' => $tenant,
                'tube' => $payload['tube'],
                'eventName' => $payload['eventName'],
                'exitCode' => $return,
                'durationSeconds' => microtime(true) - $jobStartedTime,
                'currentAttempt' => $currentAttempt,
                'maxAttempts' => $maxAttempts,
                'ttr' => $ttr
            ]
        );

        $output->write('.');

        $this->queue->delete($job);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function finishIteration(InputInterface $input, OutputInterface $output)
    {
        parent::finishIteration($input, $output);

        if ($this->jobsProcessed >= $input->getOption('jobs-before-exit')) {
            $this->shutdown();
        }
    }

    /**
     * Returns the name of the beanstalk tube to listen to for a given environment code
     *
     * @param $environmentCode
     *
     * @return string
     */
    private function generateTubeName($environmentCode): string
    {
        return $this->baseTubeName() . $environmentCode;
    }

    /**
     * Returns the base tube name that's prefixed to all tubs before the environment name in Beanstalk.
     *
     * @return string
     */
    private function baseTubeName(): string
    {
        $tube = $this->getContainer()->getParameter('vivait_delayed_event.queue.configuration.tube');

        return str_replace($this->kernel->getEnvironment(), '', $tube);
    }

    /**
     * Return the environment name from a given tube name.
     * @param string $tubeName
     * @return string
     */
    private function getTenantFromTubeName(string $tubeName): string
    {
        return str_replace($this->baseTubeName(), '', $tubeName);
    }

    /**
     * Returns an array of Tenant environments that we want to use.
     *
     * @return array
     */
    private function getTenantEnvironments(): array
    {
        $tenantList = [];

        /** @var Tenant $tenant */
        foreach ($this->kernel->getAllTenants() as $tenant) {
            $tenantList[] = self::TENANT_ENVIRONMENT_PREFIX . $tenant->getKey();
        }

        return $tenantList;
    }

    /**
     * Watches the tubes for the given array of environments.
     *
     * @param string[] $environmentList
     */
    private function watchEnvironmentTubes(array $environmentList = []): void
    {
        foreach ($environmentList as $environment) {
            $tubeName = $this->generateTubeName($environment);
            $this->logger->debug('Listening to tube: ' . $tubeName);
            $this->queue->watch($tubeName);
        }
    }

    /**
     * Waits for a job to appear in any of the tubes we are watching.
     *
     * @param int $timeout
     *
     * @return ?Job
     */
    private function waitForJob(int $timeout): ?Job
    {
        /** @var bool|Job $job */
        $job = $this->queue->reserve($timeout);

        if (is_bool($job)) {
            $job = null;
        }

        return $job;
    }

    /**
     * @param Job $job
     *
     * @return array
     */
    private function getPayload(Job $job): array
    {
        return json_decode($job->getData(), true);
    }


    private function getSymfonyConsole(): string
    {
        // 2.x
        $sfConsole = realpath($this->kernel->getRootDir() . '/console');
        if($sfConsole) {
            return $sfConsole;
        }

        // 3.x
        $sfConsole = realpath($this->kernel->getRootDir() . '/../bin/console');
        if($sfConsole) {
            return $sfConsole;
        }

        throw new \RuntimeException('Could not find the Symfony console');
    }

    /**
     * Calls the vivait:worker:process_job command for the given environment to process a job in their tube.
     *
     * @param string $environmentCode
     * @param string $eventName
     * @param string $encodedSerializedEvent
     * @param int $ttr This is the time to run set by pheanstalk.
     * @param bool $ignoreErrors
     * @param string $jobId
     *
     * @return int|null
     * @throws Exception
     */
    private function runJobForTenant(
        string $environmentCode,
        string $eventName,
        string $encodedSerializedEvent,
        int $ttr,
        bool $ignoreErrors,
        string $jobId
    ): ?int {
        $encodedEvent = base64_encode($encodedSerializedEvent);
        $processCommand = [
            'php',
            $this->getSymfonyConsole(),
            'vivait:worker:process_job',
            $eventName,
            $encodedEvent,
            $jobId,
            '--env=' . $environmentCode,
        ];

        if (!$this->kernel->isDebug()) {
            $processCommand[] = '--no-debug';
        }

        $process = new Process(implode(' ', $processCommand));
        $process->setIdleTimeout($ttr);
        $process->setTimeout($ttr);

        try {
            return $process->run();
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());

            if (!$ignoreErrors) {
                $this->logger->error('Re-throwing previous trace');
                throw $exception;
            }

            // Any other exceptions should be considered a hard fail
            return ProcessJobCommand::JOB_HARD_FAIL;
        }
    }

    /**
     * @param array|null $optionTenants
     *
     * @return array
     */
    private function getEnvironmentList(array $optionTenants = null): ?array
    {
        if (count($optionTenants) === 0) {
            return $this->getTenantEnvironments();
        }

        return $optionTenants;
    }
}
