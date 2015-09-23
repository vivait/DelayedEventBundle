<?php
namespace Vivait\DelayedEventBundle\Transformer;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\UnitOfWork;

class BasicTransformer implements TransformerInterface
{
    public function transform($data)
    {
        return $data;
    }

    public function reverseTransform($data)
    {
        return $data;
    }

    public function supports(\ReflectionProperty $property, $value)
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
        if ($value instanceOf \Serializable) {
            return true;
        }

        return false;
    }
}
