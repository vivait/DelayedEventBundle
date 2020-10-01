<?php

namespace Vivait\DelayedEventBundle\Command;

use Exception;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Vivait\DelayedEventBundle\Serializer\Exception\FailedTransformationException;
use Vivait\DelayedEventBundle\Serializer\Serializer;

/**
 * Class ProcessJobCommand
 * @package Vivait\DelayedEventBundle\Command
 */
class ProcessJobCommand extends ContainerAwareCommand
{

    public const JOB_SUCCESS = 0;
    public const JOB_SOFT_FAIL = 1;
    public const JOB_HARD_FAIL = 2;

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
     * @param KernelInterface $kernel
     * @param Logger $logger
     * @param Serializer $serializer
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        KernelInterface $kernel,
        Logger $logger,
        Serializer $serializer
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->kernel = $kernel;
        $this->logger = $logger;
        $this->serializer = $serializer;

        parent::__construct('vivait:worker:process_job');
    }

    /**
     * {}
     */
    protected function configure()
    {
        $this
            ->setDescription('When given a job, it dispatches that job into Symfony to be processed.')
            ->addArgument('eventName', InputArgument::REQUIRED, 'The event name that you want to re-dispatch')
            ->addArgument('event', InputArgument::REQUIRED, 'The event that you want to re-dispatch')
            ->addArgument('jobId', InputArgument::OPTIONAL, 'Optional ID of the job to track in the logs');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $encodedEvent = $input->getArgument('event');
        $eventName = $input->getArgument('eventName');
        $jobId = $input->getArgument('jobId') ?? 'N/A';

        $event = base64_decode($encodedEvent);
        if ($event === false) {
            $this->logger->critical("Job [{$jobId}] couldn't be decoded: " . $encodedEvent);

            return self::JOB_HARD_FAIL;
        }
        try {
            $event = $this->serializer->deserialize($event);
        } catch (FailedTransformationException $e) {
            $this->logger->critical("Job [{$jobId}] couldn't be deserialized: " . $event);

            return self::JOB_HARD_FAIL;
        }

        return $this->performJob(
            $jobId,
            $eventName,
            $event
        );
    }

    /**
     * @param string $jobId
     * @param string $eventName
     * @param object $event
     *
     * @return int
     */
    private function performJob($jobId, $eventName, $event)
    {
        try {
            $this->eventDispatcher->dispatch($eventName, $event);
        } catch (Exception $exception) {
            $this->logger->error(
                "Job [{$jobId}] threw an exception: " . $exception->getMessage(),
                [
                    'jobId' => $jobId,
                    'exception' => $exception->getMessage(),
                    'stackTrace' => $exception->getTrace()
                ]
            );

            return self::JOB_SOFT_FAIL;
        }

        return self::JOB_SUCCESS;
    }
}
