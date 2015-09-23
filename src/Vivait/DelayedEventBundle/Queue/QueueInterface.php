<?php
/*
 * Copyright CloseToMe 2011/2012
 * Released under the The MIT License
 */

namespace Vivait\DelayedEventBundle\Queue;


use Vivait\DelayedEventBundle\Queue\Beanstalkd;
use Vivait\DelayedEventBundle\Queue\Beanstalkd\Job;

interface QueueInterface {

    public function put($eventName, $data, \DateInterval $delay = null);

    /**
     * @return Job|null
     */
    public function get();

    public function delete($job);
}
