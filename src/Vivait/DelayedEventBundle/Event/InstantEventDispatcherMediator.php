<?php

namespace Vivait\DelayedEventBundle\Event;

use DateInterval;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Vivait\DelayedEventBundle\IntervalCalculator;

class InstantEventDispatcherMediator implements EventDispatcherMediatorInterface
{
    private Definition $eventDispatcherDefinition;

    public function __construct(
        Definition $eventDispatcherDefinition
    ) {
        $this->eventDispatcherDefinition = $eventDispatcherDefinition;
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
        // Register a listener for the instant event
        $this->eventDispatcherDefinition->addMethodCall(
            'addListener',
            [
                $eventName,
                [new ServiceClosureArgument(new Reference($serviceId)), $method],
                $priority,
            ],
        );
    }
}
