<?php
/*
 * Copyright CloseToMe 2011/2012
 * Released under the The MIT License
 */

namespace Vivait\DelayedEventBundle\Queue;


use DateInterval;

/**
 * Interface QueueInterface
 * @package Vivait\DelayedEventBundle\Queue
 */
interface QueueInterface {

    /**
     * @param $eventName
     * @param $data
     * @param DateInterval|null $delay
     * @param int $currentAttempt
     * @return mixed
     */
    public function put($eventName, $data, DateInterval $delay = null, $currentAttempt = 1);

    /**
     * @param null $wait_timeout Maximum time in seconds to wait for a job
     * @return null|Job
     */
    public function get($wait_timeout = null);

    /**
     * @param bool $pending Include pending jobs
     * @return bool
     */
    public function hasWaiting($pending = false);

    /**
     * @param Job $job
     * @return mixed
     */
    public function delete(Job $job);

    /**
     * @param Job $job
     * @return mixed
     */
    public function bury(Job $job);
}
