<?php

namespace Vivait\DelayedEventBundle\Event;

interface RetryableEvent
{

    /**
     * @return int
     */
    public function getMaxRetries();
}
