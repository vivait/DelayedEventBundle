<?php

namespace Vivait\DelayedEventBundle\Event;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Vivait\DelayedEventBundle\IntervalCalculator;
use Vivait\DelayedEventBundle\Queue\QueueInterface;

class EventDispatcherMediator
{
    /** @var  Definition */
    private $eventDispatcherDefinition;

    /** @var  Definition */
    private $registryDefinition;

    private $delayerId;

    private $triggers = [];

    /**
     * @param Definition $eventDispatcherDefinition
     * @param Definition $registryDefinition
     * @param $delayerId
     */
    public function __construct(Definition $eventDispatcherDefinition, Definition $registryDefinition, $delayerId)
    {
        $this->eventDispatcherDefinition = $eventDispatcherDefinition;
        $this->registryDefinition = $registryDefinition;
        $this->delayerId = $delayerId;
    }

    public function addListener($eventName, $callback, $priority, $delay) {
        // Register a listener for the trigger
        $this->registerTrigger($eventName);

        $delayedEventName = $this->generateDelayedEventName(
            $eventName,
            IntervalCalculator::convertDelayToInterval($delay)
        );

        // Take note of the delay
        $this->registryDefinition->addMethodCall('addDelay', array($eventName, $delayedEventName, $delay));

        // Register a listener for the delayed event
        $this->eventDispatcherDefinition->addMethodCall('addListenerService', array($delayedEventName, $callback, $priority));
    }

    /**
     * @param string $eventName
     */
    private function registerTrigger($eventName)
    {
        // New listeners need a trigger listener registered
        if (!isset($this->triggers[$eventName])) {
            $this->eventDispatcherDefinition->addMethodCall('addListenerService', array($eventName, array($this->delayerId, 'triggerEvent')));

            $this->triggers[$eventName] = true;
        }
    }

    /**
     * @param $eventName
     * @param $delay
     * @return string
     */
    private function generateDelayedEventName($eventName, \DateInterval $delay)
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
