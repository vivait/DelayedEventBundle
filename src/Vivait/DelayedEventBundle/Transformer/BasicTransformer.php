<?php
namespace Vivait\DelayedEventBundle\Transformer;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\UnitOfWork;
use ReflectionProperty;

/**
 * Class BasicTransformer
 * @package Vivait\DelayedEventBundle\Transformer
 */
class BasicTransformer implements TransformerInterface
{
    /**
     * @param $data
     * @return mixed
     */
    public function transform($data)
    {
        return $data;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function reverseTransform($data)
    {
        return $data;
    }

    /**
     * @param ReflectionProperty $property
     * @param $value
     * @return bool|mixed
     */
    public function supports(ReflectionProperty $property, $value)
    {
        // Support some basic types
        switch (gettype($value)) {
            case 'boolean':
            case 'integer':
            case 'double':
            case 'string':
            case 'array':
            case 'NULL':
                return true;
        }

        // Any object that is self-declared as serializable
        return $value instanceof \Serializable;
    }
}
