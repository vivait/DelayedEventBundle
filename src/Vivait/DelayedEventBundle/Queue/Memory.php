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

    public function put($eventName, $event, \DateInterval $delay = null)
    {
        $seconds = IntervalCalculator::convertDateIntervalToSeconds($delay);
        $this->jobs[$seconds][] = new Job(uniqid(), $eventName, $event);
    }

    public function get()
    {
        $currentTime = key($this->jobs);
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

}
