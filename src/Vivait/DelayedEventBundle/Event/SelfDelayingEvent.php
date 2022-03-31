<?php

namespace Vivait\DelayedEventBundle\Event;

use DateTimeImmutable;

interface SelfDelayingEvent
{

    public function getDelayedEventDateTime(): DateTimeImmutable;
}
