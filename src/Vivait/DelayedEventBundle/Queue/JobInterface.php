<?php

namespace Vivait\DelayedEventBundle\Queue;

/**
 * Interface JobInterface
 * @package Vivait\DelayedEventBundle\Queue
 */
interface JobInterface {
    public function getEventName();
    public function getEvent();
}
