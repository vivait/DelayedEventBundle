<?php

namespace Vivait\DelayedEventBundle\Queue;

use DateInterval;

interface QueueInterface {

    public function put(string $environment, string $eventName, $data, DateInterval $delay = null, int $currentAttempt = 1): void;

    /**
     * @param null $wait_timeout Maximum time in seconds to wait for a job
     * @return null|JobInterface
     */
    public function get($wait_timeout = null): ?JobInterface;

    /**
     * @param bool $pending Include pending jobs
     * @return bool
     */
    public function hasWaiting(bool $pending = false): bool;

    public function delete(JobInterface $job): void;

    public function bury(JobInterface $job): void;
}
