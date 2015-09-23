<?php

namespace Vivait\DelayedEventBundle\Queue;

use Vivait\DelayedEventBundle\IntervalCalculator;
use Vivait\DelayedEventBundle\Queue\Beanstalkd\Job;
use Vivait\DelayedEventBundle\Serializer\SerializerInterface;

class Beanstalkd implements QueueInterface
{
    const PRIORITY = 20;
    const TTL = 3600;

    protected $beanstalk;
    protected $tube;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param SerializerInterface $serializer
     * @param \Pheanstalk_PheanstalkInterface $beanstalk
     * @param string $tube
     */
    public function __construct(SerializerInterface $serializer, $beanstalk, $tube = 'delayed_events')
    {
        $this->beanstalk = $beanstalk;
        $this->tube = $tube;
        $this->serializer = $serializer;
    }

    public function put($eventName, $event, \DateInterval $delay = null)
    {
        $job = new Job($eventName, $this->serializer->serialize($event));

        $seconds = IntervalCalculator::convertDateIntervalToSeconds($delay);

        $this->beanstalk->useTube($this->tube);
        $this->beanstalk->put($job->toPheanstalk(), self::PRIORITY, $seconds, self::TTL);
    }

    public function get()
    {
        $this->beanstalk->watch($this->tube);
        $job = $this->beanstalk->reserve();

        return Job::fromPheanstalk($job);
    }

    public function delete($job)
    {
        $this->beanstalk->delete($job);
    }
}
