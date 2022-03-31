<?php
namespace Vivait\DelayedEventBundle\Transformer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use OutOfBoundsException;
use ReflectionProperty;

/**
 * Class DoctrineORM
 * @package Vivait\DelayedEventBundle\Transformer
 */
class DoctrineORM implements TransformerInterface
{
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @param $data
     * @return array|mixed
     */
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

    /**
     * @param $data
     * @return mixed
     */
    public function reverseTransform($data)
    {
        [$class, $id] = $data;
        $result = $this->doctrine->getRepository($class)->find($id);

        if ($result === null) {
            throw new OutOfBoundsException(sprintf('Could not find "%s" entity with ID "%s"', $class, $id));
        }

        return $result;
    }

    /**
     * @param ReflectionProperty $property
     * @param $value
     * @return bool|mixed
     */
    public function supports(ReflectionProperty $property, $value)
    {
        if (is_object($value)) {
            // Get the entity manager for this entity
            /** @var EntityManagerInterface $entity_manager */
            $entity_manager = $this->doctrine->getManagerForClass(get_class($value));

            if ($entity_manager && $entity_manager->contains($value) && !$entity_manager->getUnitOfWork()->isEntityScheduled($value)) {
                return true;
            }
        }

        return false;
    }
}
