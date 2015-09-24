<?php
/*
 * Copyright CloseToMe 2011/2012
 * Released under the The MIT License
 */

namespace Vivait\DelayedEventBundle\Queue;


interface QueueInterface {

    public function put($eventName, $data, \DateInterval $delay = null);

    /**
     * @return Job|null
     */
    public function get();

    public function delete(Job $job);
}
