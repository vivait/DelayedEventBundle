<?php
/*
 * Based upon SoclozEventQueueBundle
 * Copyright CloseToMe 2011/2012
 * Released under the The MIT License
 */

namespace Vivait\DelayedEventBundle\Transformer;

use ReflectionProperty;

/**
 * Interface TransformerInterface
 * @package Vivait\DelayedEventBundle\Transformer
 */
interface TransformerInterface
{
    /**
     * @param ReflectionProperty $property
     * @param $value
     * @return mixed
     */
    public function supports(ReflectionProperty $property, $value);

    /**
     * @param $data
     * @return mixed
     */
    public function transform($data);

    /**
     * @param $data
     * @return mixed
     */
    public function reverseTransform($data);

}
