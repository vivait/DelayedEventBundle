<?php

namespace Vivait\DelayedEventBundle\Queue;

use DateInterval;
use DateTimeImmutable;
use Pheanstalk\Contract\PheanstalkInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Factory\UuidFactory;
use Vivait\DelayedEventBundle\Event\PriorityAwareEvent;
use Vivait\DelayedEventBundle\Event\RetryableEvent;
use Vivait\DelayedEventBundle\Event\SelfDelayingEvent;
use Vivait\DelayedEventBundle\IntervalCalculator;
use Vivait\DelayedEventBundle\Queue\Exception\JobException;
use Vivait\DelayedEventBundle\Serializer\Exception\SerializerException;
use Vivait\DelayedEventBundle\Serializer\SerializerInterface;

class Beanstalkd implements QueueInterface
{
    public const DEFAULT_TTR = 60;

    protected PheanstalkInterface $beanstalk;
    protected string $tube;
    private SerializerInterface $serializer;
    private LoggerInterface $logger;
    private int $ttr;
    private UuidFactory $uuidFactory;

    /**
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     * @param UuidFactory $uuidFactory
     * @param PheanstalkInterface $beanstalk
     * @param string $tube
     * @param int $ttr The length of time in seconds which a process has to complete
     */
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        UuidFactory $uuidFactory,
        PheanstalkInterface$beanstalk,
        string $tube = 'delayed_events',
        int $ttr = self::DEFAULT_TTR

    ) {
        $this->logger = $logger;
        $this->beanstalk = $beanstalk;
        $this->tube = $tube;
        $this->ttr = $ttr;
        $this->serializer = $serializer;
        $this->uuidFactory = $uuidFactory;
    }

    public function put(string $environment, string $eventName, $event, DateInterval $delay = null, $currentAttempt = 1): ?JobInterface
    {
        $job = $this->serializer->serialize($event);

        $maxAttempts = 1;

        if ($event instanceof RetryableEvent) {
            $maxAttempts = $event->getMaxRetries();
        }

        $priority = PheanstalkInterface::DEFAULT_PRIORITY;

        if ($event instanceof PriorityAwareEvent) {
            $priority = $event->getPriority();
        }

        if ($event instanceof SelfDelayingEvent) {
            $now = new DateTimeImmutable();
            $eventDateTime = $event->getDelayedEventDateTime();

            if (!$eventDateTime) {
                $this->logger->info(sprintf(
                    'No delay specified for event: %s (event obj: %s)',
                    $eventName,
                    get_class($event)
                ),
                    [
                        'tube' => $this->tube,
                        'eventName' => $eventName,
                        'environment' => $environment,
                        'priority' => $priority,
                        'delaySeconds' => $delay,
                        'ttr' => $this->ttr
                    ]);

                return null;
            }

            $diff = $now->diff($eventDateTime);

            if ($diff === false || $eventDateTime < $now) {
                $delay = new DateInterval('P0D');
            } else {
                $delay = $diff;
            }
        }

        // Note: We make a delay of at least a second to give doctrine change to commit any transactions
        // This is caused by delaying an entity from a doctrine hook
        $delay = max(IntervalCalculator::convertDateIntervalToSeconds($delay), 1);

        $jobUuid = $this->uuidFactory->randomBased()->create();

        $this->beanstalk->useTube($this->tube);
        $beanstalkdJob = $this->beanstalk->put(
            json_encode(
                [
                    'id' => $jobUuid,
                    'environment' => $environment,
                    'eventName' => $eventName,
                    'event' => $job,
                    'tube' => $this->tube,
                    'maxAttempts' => $maxAttempts,
                    'currentAttempt' => $currentAttempt,
                    'ttr' => $this->ttr,
                ]
            ),
            $priority,
            $delay,
            $this->ttr
        );

        $this->logger->info(
            sprintf(
                'Job [%s] has been added to the queue',
                $jobUuid
            ),
            [
                'beanstalkId' => $beanstalkdJob->getId(),
                'jobId' => $jobUuid,
                'tube' => $this->tube,
                'eventName' => $eventName,
                'environment' => $environment,
                'priority' => $priority,
                'delaySeconds' => $delay,
                'ttr' => $this->ttr
            ]
        );

        return new Job($beanstalkdJob->getId(), $environment, $eventName, $event, $maxAttempts, $currentAttempt);
    }

    public function get($wait_timeout = null): ?JobInterface
    {
        $job = $this->beanstalk->reserveFromTube($this->tube, $wait_timeout);

        if (!$job) {
            return null;
        }

        $data = json_decode($job->getData(), true);

        try {
            $unserialized = $this->serializer->deserialize($data['event']);
        } catch (SerializerException $exception) {
            $job = new Job($job->getId(), $data['environment'], $data['eventName'], null);

            throw new JobException($job, 'Unserialization of job failed', 0, $exception);
        }

        return new Job($job->getId(), $data['environment'], $data['eventName'], $unserialized);
    }

    public function hasWaiting($pending = false): bool
    {
        $stats = $this->beanstalk->statsTube($this->tube);

        return $stats['current-jobs-ready'] > 0 || $stats['current-jobs-delayed'] > 0 || ($pending && $stats['current-jobs-reserved'] > 0);
    }

    public function delete(JobInterface $job): void
    {
        $this->beanstalk->delete($job);
    }

    public function bury(JobInterface $job): void
    {
        $this->beanstalk->bury($job);
    }
}
