<?php

namespace Vivait\DelayedEventBundle\Queue;

use DateInterval;
use Vivait\DelayedEventBundle\IntervalCalculator;

/**
 * @internal Used only for testing
 */
class Memory implements QueueInterface
{
    private $jobs = [];

    public function put(string $environment, string $eventName, $event, DateInterval $delay = null, $currentAttempt = 1): ?JobInterface
    {
        $seconds = time() - IntervalCalculator::convertDateIntervalToSeconds($delay);
        $job = new Job(uniqid('', true), $environment, $eventName, $event);
        $this->jobs[$seconds][] = $job;

        return $job;
    }

    /**
     * @param null $wait_timeout
     * @return JobInterface|null
     */
    public function get($wait_timeout = null): ?JobInterface
    {
        $currentTime = key($this->jobs);

        if ($currentTime === null) {
            return null;
        }

        $timeLeft = $currentTime - time();

        // A very rudimentary way of recreating any timeout
        if ($timeLeft) {
            if ($wait_timeout !== null && $wait_timeout < $timeLeft) {
                return null;
            }

            sleep($timeLeft);
        }

        return array_shift($this->jobs[$currentTime]);
    }

    /**
     * @param JobInterface $job
     * @return mixed|void
     */
    public function delete(JobInterface $job): void
    {
        foreach ($this->jobs as $delay => $jobs) {
            if (($key = array_search($job, $jobs)) !== false) {
                unset($this->jobs[$delay][$key]);
            }
        }
    }

    /**
     * @param JobInterface $job
     * @return mixed|void
     */
    public function bury(JobInterface $job): void
    {
        foreach ($this->jobs as $delay => $jobs) {
            if (($key = array_search($job, $jobs)) !== false) {
                // Add a second delay
                $this->jobs[$delay + 1][$key];
                unset($this->jobs[$delay][$key]);
            }
        }
    }

    /**
     * @param bool $pending
     * @return boolean
     */
    public function hasWaiting($pending = false): bool
    {
        return count($this->jobs) > 0;
    }
}
