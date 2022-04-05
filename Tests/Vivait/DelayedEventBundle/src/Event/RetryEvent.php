<?php

declare(strict_types=1);

namespace Tests\Vivait\DelayedEventBundle\src\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Vivait\DelayedEventBundle\Event\RetryableEvent;

class RetryEvent extends Event implements RetryableEvent
{

    private int $maxRetries;

    public function __construct(int $maxRetries = 3)
    {
        $this->maxRetries = $maxRetries;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }
}
