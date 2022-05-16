<?php

declare(strict_types=1);

namespace Vivait\DelayedEventBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use Vivait\DelayedEventBundle\Queue\JobInterface;

class JobEvent extends Event
{
    const EVENT_NAME = 'vivait_delayed_event.post_queue';

    private ?JobInterface $job;
    private Event $originalEvent;

    public function __construct(?JobInterface $job, Event $originalEvent)
    {
        $this->job = $job;
        $this->originalEvent = $originalEvent;
    }

    public function getJob(): ?JobInterface
    {
        return $this->job;
    }

    public function getOriginalEvent(): Event
    {
        return $this->originalEvent;
    }
}