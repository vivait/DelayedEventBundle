<?php

namespace Vivait\DelayedEventBundle\Command;

use Exception;
use InvalidArgumentException;
use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Job;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Throwable;
use Vivait\Backoff\Strategies\AbstractBackoffStrategy;
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
     * How many jobs we should process before this worker dispatcher restarts (and is then restarted by upstart)
     */
    public const JOBS_BEFORE_EXIT = 1000;

    private PheanstalkInterface $queue;

    private KernelInterface $kernel;

    private LoggerInterface $logger;

    private AbstractBackoffStrategy $backoffStrategy;

    private int $jobsProcessed = 0;

    private float $processStartedTime = 0.0;

    public function __construct(
        PheanstalkInterface $queue,
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
            )
        ;
    }

    protected function starting(InputInterface $input, OutputInterface $output): void
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

    protected function finalize(InputInterface $input, OutputInterface $output): void
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

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $tube = $this->baseTubeName();
        $this->logger->debug('Listening to tube: ' . $tube);

        $this->queue->watch($tube);

        if (
            is_numeric($input->getOption('queue-timeout'))
            && $input->getOption('queue-timeout') >= 0
        ) {
            $timeout = (int)round($input->getOption('queue-timeout'));
        } else {
            throw new InvalidArgumentException('--queue-timeout should be >= 0');
        }

        $job = $this->queue->reserveWithTimeout($timeout);

        if ($job === null) {
            $this->logger->debug("Couldn't find job before timeout. Exiting process to be restarted");

            return;
        }

        $this->jobsProcessed++;

        $payload = $this->getPayload($job);

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
                'environment' => $payload['environment'],
                'tube' => $payload['tube'],
                'eventName' => $payload['eventName'],
                'currentAttempt' => $currentAttempt,
                'maxAttempts' => $maxAttempts,
                'ttr' => $ttr
            ]
        );

        try {
            if (! isset($payload['environment'])) {
                throw new InvalidArgumentException("Job has no environment set");
            }

            $return = $this->runJobInEnvironment(
                $payload['environment'],
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
                $this->logger->error(
                    sprintf(
                        'Job [%s] has hard-failed and will be buried',
                        $jobId
                    ),
                    [
                        'beanstalkId' => $job->getId(),
                        'jobId' => $jobId,
                        'environment' => $payload['environment'],
                        'tube' => $payload['tube'],
                        'eventName' => $payload['eventName'],
                        'exitCode' => $return,
                        'durationSeconds' => microtime(true) - $jobStartedTime,
                        'currentAttempt' => $currentAttempt,
                        'maxAttempts' => $maxAttempts,
                        'ttr' => $ttr
                    ]
                );

                $this->bury($job);
                $output->write('X');

                return;
            }

            /*
             * If the command soft fails but it is the last attempt, then just bury the job as it shouldn't be retried
             */
            if ($return === ProcessJobCommand::JOB_SOFT_FAIL && $currentAttempt >= $maxAttempts) {
                $this->logger->error(
                    sprintf(
                        'Job [%s] has soft-failed but has reached the last attempt and will be buried',
                        $jobId
                    ),
                    [
                        'beanstalkId' => $job->getId(),
                        'jobId' => $jobId,
                        'environment' => $payload['environment'],
                        'tube' => $payload['tube'],
                        'eventName' => $payload['eventName'],
                        'exitCode' => $return,
                        'durationSeconds' => microtime(true) - $jobStartedTime,
                        'currentAttempt' => $currentAttempt,
                        'maxAttempts' => $maxAttempts,
                        'ttr' => $ttr
                    ]
                );
                $this->bury($job);
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

                $this->queue->useTube($payload['tube']);
                $this->queue->put(
                    json_encode(
                        [
                            'environment' => $payload['environment'],
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
                        'environment' => $payload['environment'],
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
                    'environment' => $payload['environment'],
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
            $this->bury($job);
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
                'environment' => $payload['environment'],
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
    protected function finishIteration(InputInterface $input, OutputInterface $output): void
    {
        parent::finishIteration($input, $output);

        if ($this->jobsProcessed >= $input->getOption('jobs-before-exit')) {
            $this->shutdown();
        }
    }

    /**
     * Returns the base tube name that's prefixed to all tubs before the environment name in Beanstalk.
     *
     * @return string
     */
    private function baseTubeName(): string
    {
        return $this->getContainer()->getParameter('vivait_delayed_event.queue.configuration.tube');
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
        // 4.x
        $sfConsole = realpath($this->kernel->getProjectDir() . '/bin/console');
        if ($sfConsole) {
            return $sfConsole;
        }

        throw new \RuntimeException('Could not find the Symfony console');
    }

    /**
     * Calls the vivait:worker:process_job command for the given environment to process a job in their tube.
     *
     * @param string $environment
     * @param string $eventName
     * @param string $encodedSerializedEvent
     * @param int $ttr This is the time to run set by pheanstalk.
     * @param bool $ignoreErrors
     * @param string $jobId
     *
     * @return int|null
     * @throws Exception
     */
    private function runJobInEnvironment(
        string $environment,
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
            '--env=' . $environment,
        ];

        if (! $this->kernel->isDebug()) {
            $processCommand[] = '--no-debug';
        }

        $process = new Process($processCommand);

        /**
         * TTR is the time in which the job will become visible to other workers. This can cause duplicate processing
         * if the job is not 'handled' within that time (buried, deleted, etc)
         *
         * The actual allowed execution time of the process shall be limited to 90% of the TTR to help it be buried
         * gracefully
         */
        $allowedExecutionTime = round($ttr * 0.9);
        $process->setIdleTimeout($allowedExecutionTime);
        $process->setTimeout($allowedExecutionTime);

        try {
            return $process->run();
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());

            if (! $ignoreErrors) {
                $this->logger->error('Re-throwing previous trace');
                throw $exception;
            }

            // Any other exceptions should be considered a hard fail
            return ProcessJobCommand::JOB_HARD_FAIL;
        }
    }

    /**
     * @param Job $job
     * @return void
     */
    private function bury(Job $job): void
    {
        try {
            $this->queue->bury($job);
        } catch (Throwable $exception) {
            $this->logger->critical(
                sprintf(
                    "Job couldn't be buried because: %s",
                    $exception->getMessage()
                )
            );
        }
    }
}
