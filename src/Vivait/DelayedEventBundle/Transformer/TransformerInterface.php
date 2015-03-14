<?php
/*
 * Based upon SoclozEventQueueBundle
 * Copyright CloseToMe 2011/2012
 * Released under the The MIT License
 */

namespace Vivait\DelayedEventBundle\Transformer;

interface TransformerInterface
{
    public function supports(\ReflectionProperty $property, $value);

    public function transform($data);

    public function reverseTransform($data);

}
