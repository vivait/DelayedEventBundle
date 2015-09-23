<?php

namespace Vivait\DelayedEventBundle\Queue\Beanstalkd;

use Vivait\DelayedEventBundle\Queue\JobInterface;

class Job extends \Pheanstalk_Job implements JobInterface
{
    protected $eventName;
    protected $event;

    public function __construct($eventName, $event, $id = null)
    {
        $this->eventName = $eventName;
        $this->event = $event;
        parent::__construct($id, $this->getData());
    }

    public static function fromPheanstalk(\Pheanstalk_Job $job)
    {
        list($eventName, $event) = @json_decode($job->getData(), true);

        return new self(
            $eventName,
            $event,
            $job->getId()
        );
    }

    public function toPheanstalk() {
        return json_encode([
            $this->eventName,
            $this->event
        ]);
    }

    public function getEventName()
    {
        return $this->eventName;
    }

    public function getEvent()
    {
        return $this->event;
    }
}
