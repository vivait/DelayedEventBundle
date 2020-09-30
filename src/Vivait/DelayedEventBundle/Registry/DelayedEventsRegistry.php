<?php

namespace Vivait\DelayedEventBundle\Registry;

use OutOfBoundsException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Vivait\DelayedEventBundle\IntervalCalculator;
use Vivait\DelayedEventBundle\Queue\QueueInterface;

/**
 * Class DelayedEventsRegistry
 * @package Vivait\DelayedEventBundle\Registry
 */
class DelayedEventsRegistry
{
    /**
     * @var array
     */
    private $delays;

    /**
     * @param $eventName
     * @param $delayedEventName
     * @param $delay
     */
    public function addDelay($eventName, $delayedEventName, $delay) {
        $this->delays[$eventName][$delayedEventName] = $delay;
    }

    /**
     * @param $eventName
     * @return string[][]
     */
    public function getDelays($eventName)
    {
        if (!$this->hasDelays($eventName)) {
            throw new OutOfBoundsException('No listeners found for event: '. $eventName);
        }

        return $this->delays[$eventName];
    }


    /**
     * @param $eventName
     * @return bool
     */
    public function hasDelays($eventName)
    {
        return (isset($this->delays[$eventName]));
    }
}
