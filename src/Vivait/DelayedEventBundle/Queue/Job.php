<?php

namespace Vivait\DelayedEventBundle\Queue;

use Symfony\Component\EventDispatcher\Event;

class Job implements JobInterface
{
    private $eventName;
    private $event;
    private $id;

    public function __construct($id, $eventName, $event)
    {
        $this->eventName = $eventName;
        $this->event = $event;
        $this->id = $id;
    }

    /**
     * Gets the event name
     * @return string
     */
    public function getEventName()
    {
        return $this->eventName;
    }

    /**
     * Gets the event
     * @return Event
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * Gets the job ID, as determined by the queue transport
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
}
