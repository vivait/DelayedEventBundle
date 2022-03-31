<?php

namespace Vivait\DelayedEventBundle\Event;

interface RetryableEvent
{
    public function getMaxRetries(): int;
}
