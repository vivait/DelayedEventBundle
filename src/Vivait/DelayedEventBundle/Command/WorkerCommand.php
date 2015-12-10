<?php

namespace Vivait\DelayedEventBundle\Command;

use Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Kernel;
use Vivait\DelayedEventBundle\Queue\Exception\JobException;
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

    private $waiting = false;

    /**
     * @param QueueInterface $queue
     * @param EventDispatcherInterface $eventDispatcher
     * @param Kernel $kernel
     * @param Logger $logger
     */
    function __construct(
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
            ->addOption('ignore-errors', 'i', InputOption::VALUE_NONE, 'Ignore errors and keep command alive');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ignore_errors = $input->hasOption('ignore-errors');

        // Set pause amount
        $pause = $input->getOption('pause');
        $this->setTimeout($pause);

        // Amount of time to wait for a job
        // This currently isn't supported
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

            if (!$ignore_errors) {
                $this->logger->notice("Re-throwing previous trace");
                throw $exception;
            }

            return;
        }

        if (!$job) {
            $this->logger->error("Couldn't find job before timeout");

            return;
        }

        $this->logger->notice(sprintf("Performing job %s", $job->getId()));

        try {
            $this->logger->debug(sprintf("Dispatched event %s", $job->getEventName()));
            $this->eventDispatcher->dispatch($job->getEventName(), $job->getEvent());
        } catch (\Exception $exception) {
            $this->logException('Failed to perform event', $exception);

            $this->queue->bury($job);

            if (!$ignore_errors) {
                $this->logger->notice("Re-throwing previous trace");
                throw $exception;
            }
        }

        // Delete it from the queue
        $this->queue->delete($job);

        $this->logger->info("Job finished successfully and removed");
    }

    public function shutdown()
    {
        $this->logger->error("Received shutdown signal");

        parent::shutdown();

        if ($this->waiting) {
            $this->logger->error("Shutting down instantly");

            $this->forceShutdown();
        } else {
            $this->logger->error("Waiting for job to finish before shutting down");
        }
    }

    protected function forceShutdown()
    {
        $this->kernel->shutdown();
        exit;
    }

    protected function logException($message, \Exception $exception)
    {
        $this->logger->error(
            sprintf(
                "%s with exception: %s, stack trace: %s",
                $message,
                $exception->getMessage(),
                $exception->getTraceAsString()
            )
        );

        while ($exception = $exception->getPrevious()) {
            $this->logger->error(
                sprintf(
                    "Previous exception: %s, stack trace: %s",
                    $exception->getMessage(),
                    $exception->getTraceAsString()
                )
            );
        }
    }
}
