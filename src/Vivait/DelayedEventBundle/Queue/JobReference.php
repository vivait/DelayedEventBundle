<?php

namespace Vivait\DelayedEventBundle\Queue;

/**
 * Only references the job, stores none of the related metadata. Used for deleting, burying etc.
 */
class JobReference implements JobInterface
{
    /**
     * @var mixed
     */
    private $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Gets the job ID, as determined by the queue transport
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
}
