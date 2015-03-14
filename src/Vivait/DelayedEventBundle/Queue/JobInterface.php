<?php

namespace Vivait\DelayedEventBundle\Queue;

interface JobInterface {
    public function getEventName();
    public function getEvent();
}
