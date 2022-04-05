<?php

namespace Vivait\DelayedEventBundle\Event;

use Exception;
use Symfony\Contracts\EventDispatcher\Event;
use Vivait\DelayedEventBundle\IntervalCalculator;
use Vivait\DelayedEventBundle\Registry\DelayedEventsRegistry;
use Vivait\DelayedEventBundle\Queue\QueueInterface;

class EventListenerDelayer
{
    private DelayedEventsRegistry $delayedEventsRegistry;
    private QueueInterface $queue;
    private string $environment;

    public function __construct(
        DelayedEventsRegistry $delayedEventsRegistry,
        QueueInterface $queue,
        string $environment
    ) {
        $this->delayedEventsRegistry = $delayedEventsRegistry;
        $this->queue = $queue;
        $this->environment = $environment;
    }

    /**
     * @throws Exception
     */
    public function triggerEvent(Event $event, string $eventName): void
    {
        // Get all the listeners for this event
        foreach ($this->delayedEventsRegistry->getDelays($eventName) as $delayedEventName => $delay) {
            $this->queue->put(
                $this->environment,
                $delayedEventName,
                $event,
                IntervalCalculator::convertDelayToInterval($delay),
            );
        }
    }
}
