<?php

namespace Vivait\DelayedEventBundle\Event;

interface EventDispatcherMediatorInterface
{
    /**
     * @param string $eventName
     * @param string $serviceId
     * @param string $method
     * @param $priority
     * @param $delay
     *
     * @throws \Exception
     */
    public function addListener(string $eventName, string $serviceId, string $method, $priority, $delay): void;
}