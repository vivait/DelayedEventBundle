<?php

namespace Vivait\DelayedEventBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Vivait\DelayedEventBundle\IntervalCalculator;
use Vivait\DelayedEventBundle\Registry\DelayedEventsRegistry;
use Vivait\DelayedEventBundle\Queue\QueueInterface;

/**
 * Class EventListenerDelayer
 * @package Vivait\DelayedEventBundle\Event
 */
class EventListenerDelayer
{
    /** @var \Vivait\DelayedEventBundle\Registry\DelayedEventsRegistry */
    private $delayedEventsRegistry;

    /** @var  QueueInterface */
    private $queue;

    /**
     * @param \Vivait\DelayedEventBundle\Registry\DelayedEventsRegistry $delayedEventsRegistry
     * @param QueueInterface $queue
     */
    public function __construct(DelayedEventsRegistry $delayedEventsRegistry, QueueInterface $queue)
    {
        $this->delayedEventsRegistry = $delayedEventsRegistry;
        $this->queue = $queue;
    }

    /**
     * @param Event $event
     * @param $eventName
     * @throws \Exception
     */
    public function triggerEvent(Event $event, $eventName) {
        // Get all the listeners for this event
        foreach ($this->delayedEventsRegistry->getDelays($eventName) as $delayedEventName => $delay){
            $this->queue->put($delayedEventName, $event, IntervalCalculator::convertDelayToInterval($delay));
        }
    }
}
