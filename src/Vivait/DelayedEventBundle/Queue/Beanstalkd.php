<?php

namespace Vivait\DelayedEventBundle\Queue;

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

    public function put($eventName, $event, $delay = 0)
    {
        $job = json_encode($eventName, $event);

        $this->beanstalk->useTube($this->tube);
        $this->beanstalk->put($job, self::PRIORITY, $delay, self::TTL);
    }

    public function get()
    {
        $this->beanstalk->watch($this->tube);
        $job = $this->beanstalk->reserve();

        list($inspection, $data) = @json_decode($job->getData(), true);

        return new Job($job->getId(), $data, $inspection);
    }

    public function delete($job)
    {
        $this->beanstalk->delete($job);
    }
}
