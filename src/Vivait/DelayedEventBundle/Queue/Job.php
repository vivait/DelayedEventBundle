<?php

namespace Vivait\DelayedEventBundle\Queue;

class Job implements JobInterface
{
    protected $eventName;
    protected $event;

    public function __construct($eventName, $event)
    {
        $this->eventName = $eventName;
        $this->event = $event;
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
