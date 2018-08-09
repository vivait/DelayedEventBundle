<?php

namespace Vivait\DelayedEventBundle\Command;

use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Vivait\DelayedEventBundle\Serializer\Exception\FailedTransformationException;
use Vivait\DelayedEventBundle\Serializer\Serializer;

class ProcessJobCommand extends ContainerAwareCommand
{

    const JOB_SUCCESS = 0;
    const JOB_SOFT_FAIL = 1;
    const JOB_HARD_FAIL = 2;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @param EventDispatcherInterface $eventDispatcher
     * @param KernelInterface          $kernel
     * @param Logger                   $logger
     * @param Serializer               $serializer
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, KernelInterface $kernel, Logger $logger, Serializer $serializer)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->kernel = $kernel;
        $this->logger = $logger;
        $this->serializer = $serializer;

        parent::__construct('vivait:delayed_event:process_job');
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('When given a job, it re-dispatches that job into Symfony to be processed.')
            ->addArgument('eventName', InputArgument::REQUIRED, 'The event name that you want to re-dispatch')
            ->addArgument('event', InputArgument::REQUIRED, 'The event that you want to re-dispatch')
            ->addOption('max-retries', 'r', InputOption::VALUE_REQUIRED, 'Maximum number of retries before burying job if it fails', 1)
            ->addOption('current-attempt', 'c', InputOption::VALUE_REQUIRED, 'Current attempt of the job being processed', 1);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $decodedJob = base64_decode($input->getArgument('event'));

        if ($decodedJob === false) {
            $this->logger->error("Could not decode event");

            return self::JOB_HARD_FAIL;
        }
        try {
            $event = $this->serializer->deserialize($decodedJob);
        } catch (FailedTransformationException $e) {
            $this->logger->error("Could not deserialize event");

            return self::JOB_HARD_FAIL;
        }

        $eventName = $input->getArgument('eventName');

        return $this->performJob($eventName, $event, $input->getOption('max-retries'), $input->getOption('current-attempt'));
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
     * @param string $eventName
     * @param object $event
     * @param int    $maxRetries
     * @param int    $currentAttempt
     *
     * @return int
     */
    private function performJob($eventName, $event, $maxRetries, $currentAttempt)
    {
        try {
            $this->logger->debug("Dispatched event: {$eventName}");
            $this->logger->debug("Max retries: {$maxRetries}");
            $this->logger->debug("Current attempt: {$currentAttempt}");
            $this->eventDispatcher->dispatch($eventName, $event);
        } catch (\Exception $exception) {
            $lastAttempt = $currentAttempt >= $maxRetries;

            $this->logException("Failed to perform event, attempt number {$currentAttempt} of {$maxRetries}", $exception, $lastAttempt ? 'error' : 'warning');

            if ($lastAttempt) {
                $this->logger->warning("Reached the last attempt for the job");

                return self::JOB_HARD_FAIL;
            }

            return self::JOB_SOFT_FAIL;
        }

        $this->logger->info("Job finished successfully and removed");

        return self::JOB_SUCCESS;
    }
}
