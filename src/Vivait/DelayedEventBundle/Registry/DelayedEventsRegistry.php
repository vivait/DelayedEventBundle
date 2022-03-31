<?php

namespace Vivait\DelayedEventBundle\Registry;

use OutOfBoundsException;

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
