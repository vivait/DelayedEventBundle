<?php
/*
 * Based upon SoclozEventQueueBundle
 * Copyright CloseToMe 2011/2012
 * Released under the The MIT License
 */

namespace Vivait\DelayedEventBundle\Serializer;

/**
 * Interface for serializers
 */
interface SerializerInterface
{
    /**
     * @param $event
     * @return mixed
     */
    public function serialize($event);

    /**
     * @param $serializedData
     * @return mixed
     */
    public function deserialize($serializedData);

}
