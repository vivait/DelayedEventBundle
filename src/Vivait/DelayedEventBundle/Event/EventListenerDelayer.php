<?php

namespace Vivait\DelayedEventBundle\Event;

use Exception;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Vivait\DelayedEventBundle\IntervalCalculator;
use Vivait\DelayedEventBundle\Registry\DelayedEventsRegistry;
use Vivait\DelayedEventBundle\Queue\QueueInterface;

class EventListenerDelayer
{
    private DelayedEventsRegistry $delayedEventsRegistry;
    private QueueInterface $queue;
    private EventDispatcherInterface $eventDispatcher;
    private string $environment;

    public function __construct(
        DelayedEventsRegistry $delayedEventsRegistry,
        QueueInterface $queue,
        EventDispatcherInterface $eventDispatcher,
        string $environment
    ) {
        $this->delayedEventsRegistry = $delayedEventsRegistry;
        $this->queue = $queue;
        $this->environment = $environment;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws Exception
     */
    public function triggerEvent(Event $event, string $eventName): void
    {
        // Get all the listeners for this event
        foreach ($this->delayedEventsRegistry->getDelays($eventName) as $delayedEventName => $delay) {
            $job = $this->queue->put(
                $this->environment,
                $delayedEventName,
                $event,
                IntervalCalculator::convertDelayToInterval($delay),
            );

            $this->eventDispatcher->dispatch(JobEvent::EVENT_NAME, new JobEvent($job, $event));
        }
    }
}
