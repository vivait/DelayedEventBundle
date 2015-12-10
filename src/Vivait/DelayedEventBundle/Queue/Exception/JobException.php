<?php

namespace Vivait\DelayedEventBundle\Queue\Exception;

use Exception;
use Vivait\DelayedEventBundle\Queue\Job;

class JobException extends \RuntimeException
{
    protected $job;

    public function __construct(Job $job, $message, $code = 0, Exception $previous = null)
    {
        parent::__construct(
            $message,
            $code,
            $previous
        );

        $this->job = $job;
    }

    /**
     * Gets job
     * @return Job
     */
    public function getJob()
    {
        return $this->job;
    }
}
