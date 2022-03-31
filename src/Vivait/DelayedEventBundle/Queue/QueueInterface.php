<?php

namespace Vivait\DelayedEventBundle\Queue;

use DateInterval;

interface QueueInterface {

    public function put(string $environment, string $eventName, $data, DateInterval $delay = null, int $currentAttempt = 1): void;

    /**
     * @param null $wait_timeout Maximum time in seconds to wait for a job
     * @return null|Job
     */
    public function get($wait_timeout = null): ?Job;

    /**
     * @param bool $pending Include pending jobs
     * @return bool
     */
    public function hasWaiting(bool $pending = false): bool;

    public function delete(Job $job): void;

    public function bury(Job $job): void;
}
