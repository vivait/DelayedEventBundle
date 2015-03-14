<?php

namespace Vivait\DelayedEventBundle\EventDispatcher;

use Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Vivait\DelayedEventBundle\IntervalCalculator;
use Vivait\DelayedEventBundle\Queue\QueueInterface;
use Vivait\DelayedEventBundle\Serializer\SerializerInterface;

class DelayedEventDispatcher
{

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var QueueInterface
     */
    private $queue;

    /**
     * @var array
     */
    private $listenerTriggers;

    /**
     * Constructor.
     *
     * If an EventDispatcherInterface is not provided , a new EventDispatcher
     * will be composed.
     *
     * @param SerializerInterface $serializer
     * @param QueueInterface $queue
     * @param ContainerAwareEventDispatcher|EventDispatcherInterface $dispatcher
     */
    public function __construct(SerializerInterface $serializer, QueueInterface $queue, EventDispatcherInterface $dispatcher = null)
    {
        $this->serializer = $serializer;
        $this->queue = $queue;
        $this->dispatcher = $dispatcher ?: new EventDispatcher();
    }

    /**
     * Adds a delayed event listener that listens on the specified events.
     *
     * @param string $eventName The event to listen on
     * @param callable $listener The listener
     * @param int $delay Number of seconds to delay the event
     * @param int $priority The higher this value, the earlier an event
     *                            listener will be triggered in the chain (defaults to 0)
     */
    public function addListener($eventName, $listener, $delay, $priority = 0)
    {
        $delay = IntervalCalculator::convertDelayToInterval($delay);
        $delayedEventName = $this->generateDelayedEventName($eventName, $delay);

        // Add a listener for the delayed event
        $this->dispatcher->addListener($delayedEventName, $listener, $priority);

        // Add a listener to the main dispatcher to trigger the delayed event
        $this->addListenerTrigger($eventName, $delay);
    }

    /**
     * Adds a service as a delayed event
     *
     * @param string $eventName The event to listen on
     * @param array  $callback  The service ID of the listener service & the method
     *                          name that has to be called
     * @param int $delay Number of seconds to delay the event
     * @param int $priority The higher this value, the earlier an event
     *                            listener will be triggered in the chain (defaults to 0)
     */
    public function addListenerService($eventName, $callback, $delay, $priority = 0)
    {
        if (!($this->dispatcher instanceOf ContainerAwareEventDispatcher)) {
            throw new \BadMethodCallException('Tried to add a service as a listener to a container unaware dispatcher');
        }

        $delay = IntervalCalculator::convertDelayToInterval($delay);
        $delayedEventName = $this->generateDelayedEventName($eventName, $delay);

        $this->dispatcher->addListenerService($delayedEventName, $callback, $priority);

        // Only register the trigger listener once
        if (!$this->hasListeners($delayedEventName)) {
            $this->addListenerTrigger($eventName, $delay);
        }
    }

    /**
     * @param string $eventName
     * @param int $delay The event delay, in seconds
     */
    private function addListenerTrigger($eventName, $delay)
    {
        $delay = IntervalCalculator::convertDelayToInterval($delay);
        $delayedEventName = $this->generateDelayedEventName($eventName, $delay);

        // Don't allow more than one trigger listener
        if ($this->hasListenerTrigger($delayedEventName)) {
            return;
        }

        $this->listenerTriggers[$delayedEventName] = function (Event $event) use ($delay, $delayedEventName) {
            // Serialize the event object
            $data = $this->serializer->serialize($event);
            // Add it to the queue
            $this->queue->put($delayedEventName, $data, $delay);
        };

        // Add a listener for the actual event
        $this->dispatcher->addListener(
            $eventName,
            $this->listenerTriggers[$delayedEventName]
        );
    }

    /**
     * Adds a delayed event subscriber.
     *
     * The subscriber is asked for all the events he is
     * interested in and added as a listener for these events.
     *
     * @param EventSubscriberInterface $subscriber The subscriber.
     *
     * @api
     */
    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
            if (is_string($params)) {
                throw new \InvalidArgumentException(sprintf('Subscribed events provides string "%s" when array of at least ["%s", $delay] is required', $params));
            } elseif (is_string($params[0])) {
                $this->addListener($eventName, array($subscriber, $params[0]), $params[1], isset($params[2]) ? $params[2] : 0);
            } else {
                foreach ($params as $listener) {
                    $this->addListener($eventName, array($subscriber, $listener[0]), $listener[1], isset($listener[2]) ? $listener[2] : 0);
                }
            }
        }
    }

    /**
     * Adds a service as a delayed event subscriber.
     *
     * @param string $serviceId The service ID of the subscriber service
     * @param string|EventSubscriberInterface $class     The service's class name (which must implement EventSubscriberInterface)
     */
    public function addSubscriberService($serviceId, $class)
    {
        foreach ($class::getSubscribedEvents() as $eventName => $params) {
            if (is_string($params)) {
                throw new \InvalidArgumentException(sprintf('Subscribed events provides string "%s" when array of at least ["%s", $delay] is required', $params));
            } elseif (is_string($params[0])) {
                $this->addListenerService($eventName, array($serviceId, $params[0]), $params[1], isset($params[2]) ? $params[2] : 0);
            } else {
                foreach ($params as $listener) {
                    $this->addListenerService($eventName, array($serviceId, $listener[0]), $listener[1], isset($listener[2]) ? $listener[2] : 0);
                }
            }
        }
    }

    /**
     * Removes a delayed event listener from the specified events.
     *
     * @param string|array $eventName The event(s) to remove a listener from
     * @param callable     $listener  The listener to remove
     * @param int          $delay The event delay, in seconds
     */
    public function removeListener($eventName, $listener, $delay)
    {
        $delay = IntervalCalculator::convertDelayToInterval($delay);

        if (is_array($eventName)) {
            array_walk(
                $eventName,
                function($eventName) use ($listener, $delay){
                    $this->removeListener($eventName, $listener, $delay);
                }
            );

            return;
        }

        $delayedEventName = $this->generateDelayedEventName($eventName, $delay);
        // Remove the listener for the delayed event
        $this->dispatcher->removeListener($delayedEventName, $listener);

        // Only register the trigger listener once
        if (!$this->hasListeners($delayedEventName)) {
            $this->removeListenerTrigger($eventName, $delay);
        }
    }

    /**
     * Removes an event subscriber.
     *
     * @param EventSubscriberInterface $subscriber The subscriber
     */
    public function removeSubscriber(EventSubscriberInterface $subscriber)
    {
        foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
            if (is_string($params)) {
                throw new \InvalidArgumentException(sprintf('Subscribed events provides string "%s" when array of at least ["%s", $delay] is required', $params));
            } elseif (is_string($params[0])) {
                $this->removeListener($eventName, array($subscriber, $params[0]), $params[1], isset($params[2]) ? $params[2] : 0);
            } else {
                foreach ($params as $listener) {
                    $this->removeListener($eventName, array($subscriber, $listener[0]), $listener[1], isset($listener[2]) ? $listener[2] : 0);
                }
            }
        }
    }

    /**
     * Gets the listeners of a specific event or all listeners.
     *
     * @param string $eventName The name of the event
     *
     * @return array The event listeners for the specified event, or all event listeners by event name
     */
    public function getListeners($eventName = null, $delay = null)
    {
        if ($delay !== null && $eventName !== null) {
            $delay = IntervalCalculator::convertDelayToInterval($delay);
            $eventName = $this->generateDelayedEventName($eventName, $delay);
        }

        return $this->dispatcher->getListeners($eventName);
    }

    /**
     * Checks whether an event has any registered listeners.
     *
     * @param string $eventName The name of the event
     *
     * @return bool    true if the specified event has any listeners, false otherwise
     */
    public function hasListeners($eventName = null, $delay = null)
    {
        if ($delay !== null && $eventName !== null) {
            $delay = IntervalCalculator::convertDelayToInterval($delay);
            $eventName = $this->generateDelayedEventName($eventName, $delay);
        }

        return $this->dispatcher->hasListeners($eventName);
    }

    /**
     * @param $delayedEventName
     * @return bool
     */
    protected function hasListenerTrigger($delayedEventName)
    {
        return (isset($this->listenerTriggers[$delayedEventName]));
    }

    /**
     * @param string $eventName The event(s) to remove a listener from
     * @param int          $delay The event delay, in seconds
     */
    protected function removeListenerTrigger($eventName, $delay)
    {
        $delay = IntervalCalculator::convertDelayToInterval($delay);
        $delayedEventName = $this->generateDelayedEventName($eventName, $delay);

        if ($this->hasListenerTrigger($delayedEventName)) {
            $this->dispatcher->removeListener($eventName, $this->listenerTriggers[$delayedEventName]);
        }
    }

    /**
     * @param $eventName
     * @param $delay
     * @return string
     */
    public function generateDelayedEventName($eventName, \DateInterval $delay)
    {
        // Months & years are ambiguous so lets not convert them
        $delayString = sprintf('PT%sY%sM%sS', $delay->y, $delay->m, $this->convertIntervalToFactors($delay));

        return sprintf('%s_delayed_by_%s', $eventName, $delayString);
    }

    private function convertIntervalToFactors(\DateInterval $interval) {
        $days    = $interval->d;
        $hours   = $interval->h + ($days * 24);
        $minutes = $interval->i + ($hours * 60);
        return     $interval->s + ($minutes * 60);
    }
}
