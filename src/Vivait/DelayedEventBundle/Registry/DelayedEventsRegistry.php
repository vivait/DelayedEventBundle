<?php

namespace Vivait\DelayedEventBundle\Registry;

use OutOfBoundsException;

class DelayedEventsRegistry
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private $delays;

    /**
     * @param string $eventName
     * @param string $delayedEventName
     * @param mixed $delay
     */
    public function addDelay(string $eventName, string $delayedEventName, $delay)
    {
        $this->delays[$eventName][$delayedEventName] = $delay;
    }

    /**
     * @param string $eventName
     *
     * @return array<string, mixed>
     */
    public function getDelays(string $eventName): array
    {
        if (! $this->hasDelays($eventName)) {
            throw new OutOfBoundsException('No listeners found for event: '. $eventName);
        }

        return $this->delays[$eventName];
    }

    public function hasDelays(string $eventName): bool
    {
        return (isset($this->delays[$eventName]));
    }
}
