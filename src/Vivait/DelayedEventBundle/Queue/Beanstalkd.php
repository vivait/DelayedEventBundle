<?php

namespace Vivait\DelayedEventBundle\Queue;

use Vivait\DelayedEventBundle\IntervalCalculator;
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
        $job = $this->serializer->serialize($event);

        $seconds = IntervalCalculator::convertDateIntervalToSeconds($delay);

        $this->beanstalk->putInTube($this->tube, json_encode(
            [
                'eventName' => $eventName,
                'event' => $job
            ]
        ), \Pheanstalk_PheanstalkInterface::DEFAULT_PRIORITY, $seconds);
    }

    public function get()
    {
        $job = $this->beanstalk->reserveFromTube($this->tube);
        $data = json_decode($job->getData(), true);

        return new Job($job->getId(), $data['eventName'], $this->serializer->deserialize($data['event']));
    }

    public function delete(Job $job)
    {
        $this->beanstalk->delete($job);
    }
}
