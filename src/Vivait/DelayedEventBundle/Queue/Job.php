<?php

namespace Vivait\DelayedEventBundle\Queue\Beanstalkd;

use Vivait\DelayedEventBundle\Queue\JobInterface;

class Job extends \Pheanstalk_Job implements JobInterface
{
    protected $eventName;
    protected $event;

    public function __construct($id, $eventName, $event)
    {
        $this->eventName = $eventName;
        $this->event = $event;
    }

    public static function fromPheanstalkJob(\Pheanstalk_Job $job) {
        list($eventName, $event) = @json_decode($job->getData(), true);

        return new self($job->getId(), $eventName, $event);
    }

    public function getEventName()
    {
        return $this->eventName;
    }

    public function getEvent()
    {
        return $this->eventName;
    }

    public function getData() {
        return json_encode($this->eventName, $this->event);
    }
}
