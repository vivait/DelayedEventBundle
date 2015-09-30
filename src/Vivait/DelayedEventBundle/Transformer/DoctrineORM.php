<?php
namespace Vivait\DelayedEventBundle\Transformer;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\UnitOfWork;

class DoctrineORM implements TransformerInterface
{
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public function transform($data)
    {
        $class = get_class($data);

        $entity_manager = $this->doctrine->getManagerForClass($class);

        /* @var UnitOfWork $uow */
        $uow = $entity_manager->getUnitOfWork();
        $id = $uow->getSingleIdentifierValue($data);

        return [
            $class,
            $id
        ];
    }

    public function reverseTransform($data)
    {
        list($class, $id) = $data;
        $result = $this->doctrine->getRepository($class)->find($id);

        if ($result === null) {
            throw new \OutOfBoundsException(sprintf('Could not find "%s" entity with ID "%s"', $class, $id));
        }

        return $result;
    }

    public function supports(\ReflectionProperty $property, $value)
    {
        if (is_object($value)) {
            // Get the entity manager for this entity
            $entity_manager = $this->doctrine->getManagerForClass(get_class($value));

            if ($entity_manager && $entity_manager->contains($value)) {
                return true;
            }
        }

        return false;
    }
}
