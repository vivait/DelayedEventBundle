<?php

declare(strict_types=1);

namespace Vivait\DelayedEventBundle\Event;

/**
 * Interface PriorityAwareEvent
 * @package Vivait\DelayedEventBundle\Event
 */
interface PriorityAwareEvent
{

    /**
     * @return int between 0 and 4294967295. Most urgent: 0, least urgent: 4294967295.
     */
    public function getPriority(): int;
}
