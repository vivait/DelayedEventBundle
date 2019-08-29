<?php

namespace Vivait\DelayedEventBundle\Queue;

use DateInterval;
use Pheanstalk\PheanstalkInterface;
use Vivait\DelayedEventBundle\Event\PriorityAwareEvent;
use Vivait\DelayedEventBundle\Event\RetryableEvent;
use Vivait\DelayedEventBundle\IntervalCalculator;
use Vivait\DelayedEventBundle\Queue\Exception\JobException;
use Vivait\DelayedEventBundle\Serializer\Exception\SerializerException;
use Vivait\DelayedEventBundle\Serializer\SerializerInterface;

class Beanstalkd implements QueueInterface
{
    protected $beanstalk;
    protected $tube;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param SerializerInterface $serializer
     * @param PheanstalkInterface $beanstalk
     * @param string $tube
     */
    public function __construct(SerializerInterface $serializer, $beanstalk, $tube = 'delayed_events')
    {
        $this->beanstalk = $beanstalk;
        $this->tube = $tube;
        $this->serializer = $serializer;
    }

    public function put($eventName, $event, DateInterval $delay = null, $currentAttempt = 1)
    {
        $job = $this->serializer->serialize($event);

        $maxRetries = 1;

        if ($event instanceof RetryableEvent) {
            $maxRetries = $event->getMaxRetries();
        }

        $priority = PheanstalkInterface::DEFAULT_PRIORITY;

        if ($event instanceof PriorityAwareEvent) {
            $priority = $event->getPriority();
        }

        // Note: We make a delay of at least a second to give doctrine change to commit any transactions
        // This is caused by delaying an entity from a doctrine hook
        $seconds = max(IntervalCalculator::convertDateIntervalToSeconds($delay), 1);

        $this->beanstalk->putInTube(
            $this->tube,
            json_encode(
            [
                'eventName' => $eventName,
                'event' => $job,
                'tube' => $this->tube,
                'maxRetries' => $maxRetries,
                'currentAttempt' => $currentAttempt,
            ]
            ),
            $priority,
            $seconds
        );
    }

    public function get($wait_timeout = null)
    {

        $job = $this->beanstalk->reserveFromTube($this->tube, $wait_timeout);

        if (!$job) {
            return false;
        }

        $data = json_decode($job->getData(), true);

        try {
            $unserialized = $this->serializer->deserialize($data['event']);
        }
        catch (SerializerException $exception) {
            $job = new Job($job->getId(), $data['eventName'], null);

            throw new JobException($job, 'Unserialization of job failed', 0, $exception);
        }

        return new Job($job->getId(), $data['eventName'], $unserialized);
    }

    public function hasWaiting($pending = false)
    {
        $stats = $this->beanstalk->statsTube($this->tube);

        return $stats['current-jobs-ready'] > 0 || $stats['current-jobs-delayed'] > 0 || ($pending && $stats['current-jobs-reserved'] > 0);
    }

    public function delete(Job $job)
    {
        $this->beanstalk->delete($job);
    }

    public function bury(Job $job)
    {
        $this->beanstalk->bury($job);
    }
}
