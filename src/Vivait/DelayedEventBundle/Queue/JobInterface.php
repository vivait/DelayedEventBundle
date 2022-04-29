<?php

namespace Vivait\DelayedEventBundle\Queue;

interface JobInterface {
    public function getId();
    public function getEventName();
    public function getEvent();
}
