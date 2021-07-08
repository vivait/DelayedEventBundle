<?php

namespace Vivait\DelayedEventBundle\Event;

use DateTimeImmutable;

/**
 * Interface SelfDelayingEvent
 * @package Vivait\DelayedEventBundle\Event
 */
interface SelfDelayingEvent
{

    public function getDelayedEventDateTime(): DateTimeImmutable;
}
