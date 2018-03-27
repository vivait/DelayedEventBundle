<?php

namespace Vivait\DelayedEventBundle\Queue;

use Vivait\DelayedEventBundle\IntervalCalculator;
use Vivait\DelayedEventBundle\Serializer\SerializerInterface;

/**
 * @internal Used only for testing
 */
class Memory implements QueueInterface
{
    private $jobs = [];

    public function put($eventName, $event, \DateInterval $delay = null, $currentAttempt = 1)
    {
        $seconds = time() - IntervalCalculator::convertDateIntervalToSeconds($delay);
        $this->jobs[$seconds][] = new Job(uniqid(), $eventName, $event);
    }

    public function get($wait_timeout = null)
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
            else {
                sleep($timeLeft);
            }
        }

        return array_shift($this->jobs[$currentTime]);
    }

    public function delete(Job $job)
    {
        foreach ($this->jobs as $delay => $jobs) {
            if (($key = array_search($job, $jobs)) !== false) {
                unset($this->jobs[$delay][$key]);
            }
        }
    }

    public function bury(Job $job)
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
     * @return boolean
     */
    public function hasWaiting($pending = false)
    {
        return count($this->jobs) > 0;
    }
}
