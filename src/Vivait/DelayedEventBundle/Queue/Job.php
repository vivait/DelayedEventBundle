<?php

namespace Vivait\DelayedEventBundle\Queue;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class Job
 * @package Vivait\DelayedEventBundle\Queue
 */
class Job implements JobInterface
{
    /**
     * @var string
     */
    private $eventName;

    /**
     * @var Event
     */
    private $event;

    /**
     * @var mixed
     */
    private $id;

    /**
     * @var int
     */
    private $attempts;

    /**
     * @var int
     */
    private $maxAttempts;

    /**
     * Job constructor.
     * @param $id
     * @param $eventName
     * @param $event
     * @param int $maxAttempts
     * @param int $attempts
     */
    public function __construct($id, $eventName, $event, $maxAttempts = 1, $attempts = 1)
    {
        $this->eventName = $eventName;
        $this->event = $event;
        $this->id = $id;
        $this->attempts = $attempts;
        $this->maxAttempts = $maxAttempts;
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

    /**
     * @return int
     */
    public function getAttempts()
    {
        return $this->attempts;
    }

    /**
     * @return int
     */
    public function getMaxAttempts()
    {
        return $this->maxAttempts;
    }
}
