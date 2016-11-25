<?php

namespace Vivait\DelayedEventBundle\Command;

use Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Kernel;
use Vivait\DelayedEventBundle\Queue\Exception\JobException;
use Vivait\DelayedEventBundle\Queue\Job;
use Vivait\DelayedEventBundle\Queue\QueueInterface;
use Wrep\Daemonizable\Command\EndlessCommand;

class WorkerCommand extends EndlessCommand
{

    const DEFAULT_TIMEOUT      = 0;
    const DEFAULT_WAIT_TIMEOUT = null;

    /**
     * @var QueueInterface
     */
    private $queue;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var bool
     */
    private $waiting = false;

    /**
     * @param QueueInterface           $queue
     * @param EventDispatcherInterface $eventDispatcher
     * @param Kernel                   $kernel
     * @param Logger                   $logger
     */
    public function __construct(
        QueueInterface $queue,
        EventDispatcherInterface $eventDispatcher,
        Kernel $kernel,
        Logger $logger
    ) {
        $this->queue = $queue;
        $this->eventDispatcher = $eventDispatcher;
        $this->kernel = $kernel;
        $this->logger = $logger;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('vivait:delayed_event:worker')
            ->setDescription('Runs the delayed event worker')
            ->addOption(
                'pause',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Time to pause between iterations',
                self::DEFAULT_TIMEOUT
            )
            ->addOption(
                'wait-timeout',
                't',
                InputOption::VALUE_OPTIONAL,
                'Maximum time to wait for something to run - use with --run-once when debugging',
                self::DEFAULT_WAIT_TIMEOUT
            )
            ->addOption(
                'ignore-errors',
                'i',
                InputOption::VALUE_NONE,
                'Ignore errors and keep command alive'
            )
            ->addOption(
                'max-retries',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of retries before burying job if it fails',
                0
            )
        ;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ignoreErrors = $input->getOption('ignore-errors');
        $maxRetries = $input->getOption('max-retries');

        // Set pause amount
        $pause = $input->getOption('pause');
        $this->setTimeout($pause);

        // Amount of time to wait for a job. This currently isn't supported.
        $wait_timeout = $input->getOption('wait-timeout');

        try {
            $this->waiting = true;
            $job = $this->queue->get($wait_timeout);
            $this->waiting = false;
        } catch (JobException $exception) {
            $this->logException('Job failed to unserialize', $exception);

            if (($job = $exception->getJob())) {
                $this->logger->notice(sprintf("Burying job %s", $job->getId()));
                $this->queue->bury($job);
            }

            if ( ! $ignoreErrors) {
                $this->logger->notice("Re-throwing previous trace");
                throw $exception;
            }

            return;
        }

        if ( ! $job) {
            $this->logger->error("Couldn't find job before timeout");

            return;
        }

        $this->logger->notice(sprintf("Performing job %s", $job->getId()));

        $this->performJob($job, $ignoreErrors, $maxRetries);
    }

    /**
     * Try and shutdown.
     */
    public function shutdown()
    {
        $this->logger->info("Received shutdown signal");

        parent::shutdown();

        if ($this->waiting) {
            $this->forceShutdown();
        } else {
            $this->logger->warning("Waiting for job to finish before shutting down");
        }
    }

    /**
     * Force shutdown of command.
     */
    protected function forceShutdown()
    {
        $this->logger->info("Shutting down instantly");

        $this->kernel->shutdown();
        exit;
    }

    /**
     * @param string     $message
     * @param \Exception $exception
     * @param string     $level
     */
    protected function logException($message, \Exception $exception, $level = 'error')
    {
        $this->logger->log(
            $level,
            sprintf(
                "%s with exception: %s, stack trace: %s",
                $message,
                $exception->getMessage(),
                $exception->getTraceAsString()
            )
        );

        while ($exception = $exception->getPrevious()) {
            $this->logger->log(
                $level,
                sprintf(
                    "Previous exception: %s, stack trace: %s",
                    $exception->getMessage(),
                    $exception->getTraceAsString()
                )
            );
        }
    }

    /**
     * @param Job         $job
     * @param bool        $ignoreErrors
     * @param int|string  $maxRetries
     * @param int         $currentAttemptNumber
     *
     * @throws \Exception
     */
    private function performJob(Job $job, $ignoreErrors, $maxRetries, $currentAttemptNumber = 0)
    {
        $succeeded = false;
        
        try {
            $this->logger->debug(sprintf("Dispatched event %s", $job->getEventName()));
            $this->eventDispatcher->dispatch($job->getEventName(), $job->getEvent());
            $succeeded = true;
        } catch (\Exception $exception) {
            $lastAttempt = $currentAttemptNumber === (int) $maxRetries;
            
            $this->logException(
                'Failed to perform event, attempt number ' . $currentAttemptNumber,
                $exception,
                $lastAttempt ? 'error' : 'warning'
            );

            if ($lastAttempt) {
                $this->logger->warning("Burying job");
                $this->queue->bury($job);
            }

            if ( ! $ignoreErrors && $currentAttemptNumber === (int) $maxRetries) {
                $this->logger->notice("Re-throwing previous trace");
                throw $exception;
            }
        }
        
        if ($succeeded) {
            // Delete it from the queue
            $this->queue->delete($job);

            $this->logger->info("Job finished successfully and removed");
            
            return;
        }
        
        $this->performJob($job, $ignoreErrors, $maxRetries, $currentAttemptNumber + 1);
    }
}
