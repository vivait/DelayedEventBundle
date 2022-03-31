<?php

namespace Vivait\DelayedEventBundle\Queue;

use Symfony\Contracts\EventDispatcher\Event;

class Job implements JobInterface
{
    private string $eventName;

    private Event $event;

    /**
     * @var mixed
     */
    private $id;

    private int $attempts;

    private int $maxAttempts;

    private string $environment;

    public function __construct($id, string $environment, string $eventName, Event $event, int $maxAttempts = 1, int $attempts = 1)
    {
        $this->eventName = $eventName;
        $this->event = $event;
        $this->id = $id;
        $this->attempts = $attempts;
        $this->maxAttempts = $maxAttempts;
        $this->environment = $environment;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getEvent(): Event
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

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }
}
