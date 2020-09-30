<?php

namespace Vivait\DelayedEventBundle\Event;

/**
 * Interface RetryableEvent
 * @package Vivait\DelayedEventBundle\Event
 */
interface RetryableEvent
{

    /**
     * @return int
     */
    public function getMaxRetries();
}
