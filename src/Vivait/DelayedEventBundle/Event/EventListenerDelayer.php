<?php

namespace Vivait\DelayedEventBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Vivait\DelayedEventBundle\IntervalCalculator;
use Vivait\DelayedEventBundle\Registry\DelayedEventsRegistry;
use Vivait\DelayedEventBundle\Queue\QueueInterface;

/**
 * Class EventListenerDelayer
 * @package Vivait\DelayedEventBundle\Event
 */
class EventListenerDelayer
{
    private DelayedEventsRegistry $delayedEventsRegistry;
    private QueueInterface $queue;
    private string $environment;

    /**
     * @param DelayedEventsRegistry $delayedEventsRegistry
     * @param QueueInterface $queue
     */
    public function __construct(DelayedEventsRegistry $delayedEventsRegistry, QueueInterface $queue, string $environment)
    {
        $this->delayedEventsRegistry = $delayedEventsRegistry;
        $this->queue = $queue;
        $this->environment = $environment;
    }

    /**
     * @param Event $event
     * @param $eventName
     * @throws \Exception
     */
    public function triggerEvent(Event $event, $eventName) {
        // Get all the listeners for this event
        foreach ($this->delayedEventsRegistry->getDelays($eventName) as $delayedEventName => $delay){
            $this->queue->put($this->environment, $delayedEventName, $event, IntervalCalculator::convertDelayToInterval($delay));
        }
    }
}
