<?php

namespace Vivait\DelayedEventBundle\Command;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use Vivait\DelayedEventBundle\Exception\TerminalEventException;
use Vivait\DelayedEventBundle\Exception\TransientEventException;
use Vivait\DelayedEventBundle\Job;
use Vivait\DelayedEventBundle\Serializer\SerializerInterface;

class ProcessJobCommand extends Command
{

    public const JOB_SUCCESS = 0;
    public const JOB_SOFT_FAIL = 1;
    public const JOB_HARD_FAIL = 2;

    private EventDispatcherInterface $eventDispatcher;

    private LoggerInterface $logger;

    private SerializerInterface $serializer;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger,
        SerializerInterface $serializer
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->serializer = $serializer;

        parent::__construct('vivait:worker:process_job');
    }

    protected function configure()
    {
        $this
            ->setDescription('When given a job, it dispatches that job into Symfony to be processed.')
            ->addArgument('eventName', InputArgument::REQUIRED, 'The event name that you want to re-dispatch')
            ->addArgument('event', InputArgument::REQUIRED, 'The event that you want to re-dispatch')
            ->addArgument('jobId', InputArgument::OPTIONAL, 'Optional ID of the job to track in the logs')
            ->addArgument('beanstalkId', InputArgument::OPTIONAL, 'Optional ID of the job in beanstalk to poke')
        ;
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
        $beanstalkId = $input->getArgument('beanstalkId');

        $event = base64_decode($encodedEvent);
        if ($event === false) {
            $this->logger->critical(
                "Event couldn't be decoded",
                [
                    'jobId' => $jobId,
                    'encodedEvent' => $encodedEvent
                ]
            );

            return self::JOB_HARD_FAIL;
        }
        try {
            $event = $this->serializer->deserialize($event);
        } catch (Throwable $exception) {
            $this->logger->critical(
                "Event couldn't be deserialized",
                [
                    'jobId' => $jobId,
                    'decodedEvent' => $event,
                    'exception' => $exception->getMessage(),
                    'stackTrace' => $exception->getTrace()
                ]
            );

            return self::JOB_HARD_FAIL;
        }

        return $this->performJob(
            $jobId,
            $beanstalkId,
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
    private function performJob($jobId, $beanstalkId, $eventName, $event)
    {
        Job::$id = $jobId;
        Job::$beanstalkId = $beanstalkId;

        try {
            $this->eventDispatcher->dispatch($event, $eventName);
        } catch (TerminalEventException $exception) {
            // Unwrap inner exception that caused the terminal exception
            $exception = $exception->getPrevious();

            $this->logger->error(
                'Job threw a terminal exception',
                [
                    'jobId' => $jobId,
                    'exception' => $exception->getMessage(),
                    'stackTrace' => $exception->getTrace()
                ]
            );

            return self::JOB_HARD_FAIL;
        } catch (TransientEventException $exception) {
            // Unwrap inner exception that caused the transient exception
            $exception = $exception->getPrevious();

            $this->logger->warning(
                'Job threw a transient exception',
                [
                    'jobId' => $jobId,
                    'exception' => $exception->getMessage(),
                    'stackTrace' => $exception->getTrace()
                ]
            );

            return self::JOB_SOFT_FAIL;
        } catch (Throwable $exception) {
            $this->logger->error(
                'Job threw an unhandled exception',
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
