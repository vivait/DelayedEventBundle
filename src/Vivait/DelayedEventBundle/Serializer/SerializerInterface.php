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
    public function serialize($event);

    public function deserialize($serializedData);

}
