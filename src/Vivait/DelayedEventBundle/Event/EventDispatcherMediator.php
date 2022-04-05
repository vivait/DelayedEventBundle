<?php

namespace Vivait\DelayedEventBundle\Event;

use DateInterval;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Vivait\DelayedEventBundle\IntervalCalculator;

class EventDispatcherMediator
{
    private Definition $eventDispatcherDefinition;
    private Definition $registryDefinition;
    private string $delayerId;
    private array $triggers = [];

    public function __construct(
        Definition $eventDispatcherDefinition,
        Definition $registryDefinition,
        string $delayerId
    ) {
        $this->eventDispatcherDefinition = $eventDispatcherDefinition;
        $this->registryDefinition = $registryDefinition;
        $this->delayerId = $delayerId;
    }

    /**
     * @param string $eventName
     * @param string $serviceId
     * @param string $method
     * @param $priority
     * @param $delay
     *
     * @throws \Exception
     */
    public function addListener(string $eventName, string $serviceId, string $method, $priority, $delay): void
    {
        // Register a listener for the trigger
        $this->registerTrigger($eventName);

        $delayedEventName = $this->generateDelayedEventName(
            $eventName,
            IntervalCalculator::convertDelayToInterval($delay)
        );

        // Take note of the delay
        $this->registryDefinition->addMethodCall('addDelay', array($eventName, $delayedEventName, $delay));

        // Register a listener for the delayed event
        $this->eventDispatcherDefinition->addMethodCall(
            'addListener',
            [
                $delayedEventName,
                [new ServiceClosureArgument(new Reference($serviceId)), $method],
                $priority,
            ],
        );
    }

    private function registerTrigger(string $eventName): void
    {
        // New listeners need a trigger listener registered
        if (! isset($this->triggers[$eventName])) {
            $this->eventDispatcherDefinition->addMethodCall(
                'addListener',
                [
                    $eventName,
                    [new ServiceClosureArgument(new Reference($this->delayerId)), 'triggerEvent'],
                ],
            );

            $this->triggers[$eventName] = true;
        }
    }

    private function generateDelayedEventName(string $eventName, DateInterval $delay): string
    {
        // Months & years are ambiguous so lets not convert them
        $delayString = sprintf('PT%sY%sM%sS', $delay->y, $delay->m, $this->convertIntervalToFactors($delay));

        return sprintf('%s_delayed_by_%s', $eventName, $delayString);
    }

    /**
     * @param DateInterval $interval
     *
     * @return float|int
     */
    private function convertIntervalToFactors(DateInterval $interval)
    {
        $days = $interval->d;
        $hours = $interval->h + ($days * 24);
        $minutes = $interval->i + ($hours * 60);

        return $interval->s + ($minutes * 60);
    }
}
