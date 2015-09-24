<?php

namespace Vivait\DelayedEventBundle\Queue;

use Vivait\DelayedEventBundle\IntervalCalculator;
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
        $this->beanstalk->useTube($this->tube);
    }

    public function put($eventName, $event, \DateInterval $delay = null)
    {
        $job = $this->serializer->serialize($event);

        $seconds = IntervalCalculator::convertDateIntervalToSeconds($delay);

        $this->beanstalk->put(json_encode(
            [
                'eventName' => $eventName,
                'event' => $job
            ]
        ), self::PRIORITY, $seconds, self::TTL);
    }

    public function get()
    {
        $this->beanstalk->watch($this->tube);
        $job = $this->beanstalk->reserve();
        $data = json_decode($this->serializer->deserialize($job->getData()), true);

        return new Job($data['eventName'], $data['job']);
    }

    public function delete($job)
    {
        $this->beanstalk->delete($job);
    }
}
