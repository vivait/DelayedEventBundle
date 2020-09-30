<?php

namespace Vivait\DelayedEventBundle\Queue;

use DateInterval;
use Pheanstalk\PheanstalkInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Vivait\DelayedEventBundle\Event\PriorityAwareEvent;
use Vivait\DelayedEventBundle\Event\RetryableEvent;
use Vivait\DelayedEventBundle\IntervalCalculator;
use Vivait\DelayedEventBundle\Queue\Exception\JobException;
use Vivait\DelayedEventBundle\Serializer\Exception\SerializerException;
use Vivait\DelayedEventBundle\Serializer\SerializerInterface;

/**
 * Class Beanstalkd
 * @package Vivait\DelayedEventBundle\Queue
 */
class Beanstalkd implements QueueInterface
{
    protected $beanstalk;
    protected $tube;

    public const DEFAULT_TTR = 60;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $ttr;

    /**
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     * @param PheanstalkInterface $beanstalk
     * @param string $tube
     * @param int $ttr The length of time in seconds which a process has to complete
     */
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        $beanstalk,
        $tube = 'delayed_events',
        $ttr = self::DEFAULT_TTR
    ) {
        $this->logger = $logger;
        $this->beanstalk = $beanstalk;
        $this->tube = $tube;
        $this->ttr = $ttr;
        $this->serializer = $serializer;
    }

    /**
     * @param $eventName
     * @param $event
     * @param DateInterval|null $delay
     * @param int $currentAttempt
     */
    public function put($eventName, $event, DateInterval $delay = null, $currentAttempt = 1)
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

        // Note: We make a delay of at least a second to give doctrine change to commit any transactions
        // This is caused by delaying an entity from a doctrine hook
        $seconds = max(IntervalCalculator::convertDateIntervalToSeconds($delay), 1);

        $jobId = (string)Uuid::uuid4();

        $beanstalkdId = $this->beanstalk->putInTube(
            $this->tube,
            json_encode(
                [
                    'id' => $jobId,
                    'eventName' => $eventName,
                    'event' => $job,
                    'tube' => $this->tube,
                    'maxAttempts' => $maxAttempts,
                    'currentAttempt' => $currentAttempt,
                    'ttr' => $this->ttr,
                ]
            ),
            $priority,
            $seconds,
            $this->ttr
        );

        $this->logger->info(
            sprintf(
                'Job [%s] has been added to the queue',
                $jobId
            ),
            [
                'beanstalkId' => $beanstalkdId,
                'jobId' => $jobId,
                'tube' => $this->tube,
                'eventName' => $eventName,
                'priority' => $priority,
                'delaySeconds' => $seconds,
                'ttr' => $this->ttr
            ]
        );
    }

    /**
     * @param null $wait_timeout
     * @return false|Job|null
     */
    public function get($wait_timeout = null)
    {
        $job = $this->beanstalk->reserveFromTube($this->tube, $wait_timeout);

        if (!$job) {
            return false;
        }

        $data = json_decode($job->getData(), true);

        try {
            $unserialized = $this->serializer->deserialize($data['event']);
        } catch (SerializerException $exception) {
            $job = new Job($job->getId(), $data['eventName'], null);

            throw new JobException($job, 'Unserialization of job failed', 0, $exception);
        }

        return new Job($job->getId(), $data['eventName'], $unserialized);
    }

    /**
     * @param false $pending
     * @return bool
     */
    public function hasWaiting($pending = false)
    {
        $stats = $this->beanstalk->statsTube($this->tube);

        return $stats['current-jobs-ready'] > 0 || $stats['current-jobs-delayed'] > 0 || ($pending && $stats['current-jobs-reserved'] > 0);
    }

    /**
     * @param Job $job
     */
    public function delete(Job $job)
    {
        $this->beanstalk->delete($job);
    }

    /**
     * @param Job $job
     */
    public function bury(Job $job)
    {
        $this->beanstalk->bury($job);
    }
}
