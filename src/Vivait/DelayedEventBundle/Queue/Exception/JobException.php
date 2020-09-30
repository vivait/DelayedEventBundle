<?php

namespace Vivait\DelayedEventBundle\Queue\Exception;

use Exception;
use RuntimeException;
use Vivait\DelayedEventBundle\Queue\Job;

/**
 * Class JobException
 * @package Vivait\DelayedEventBundle\Queue\Exception
 */
class JobException extends RuntimeException
{
    protected $job;

    /**
     * JobException constructor.
     * @param Job $job
     * @param $message
     * @param int $code
     * @param Exception|null $previous
     */
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
