<?php

namespace Vivait\DelayedEventBundle\Queue;

use Vivait\DelayedEventBundle\IntervalCalculator;
use Vivait\DelayedEventBundle\Queue\Beanstalkd\Job;

class Beanstalkd implements QueueInterface
{
    const PRIORITY = 20;
    const TTL = 3600;

    protected $beanstalk;
    protected $tube;

    /**
     * @param \Pheanstalk_PheanstalkInterface $beanstalk
     * @param string $tube
     */
    public function __construct($beanstalk, $tube)
    {
        $this->beanstalk = $beanstalk;
        $this->tube = $tube;
    }

    public function put($eventName, $event, \DateInterval $delay = null)
    {
        $job = json_encode($eventName, $event);

        $seconds = IntervalCalculator::convertDateIntervalToSeconds($delay);

        $this->beanstalk->useTube($this->tube);
        $this->beanstalk->put($job, self::PRIORITY, $seconds, self::TTL);
    }

    public function get()
    {
        $this->beanstalk->watch($this->tube);
        $job = $this->beanstalk->reserve();

        return Job::fromPheanstalkJob($job);
    }

    public function delete($job)
    {
        $this->beanstalk->delete($job);
    }
}
