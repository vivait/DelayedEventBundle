<?php
/*
 * Copyright CloseToMe 2011/2012
 * Released under the The MIT License
 */

namespace Vivait\DelayedEventBundle\Queue;


interface QueueInterface {

    public function put($eventName, $data, \DateInterval $delay = null);

    /**
     * @param int $wait_timeout Maximum time in seconds to wait for a job
     * @return null|Job
     */
    public function get($wait_timeout = null);

    /**
     * @param bool $pending Include pending jobs
     * @return bool
     */
    public function hasWaiting($pending = false);

    public function delete(Job $job);

    public function bury(Job $job);
}
