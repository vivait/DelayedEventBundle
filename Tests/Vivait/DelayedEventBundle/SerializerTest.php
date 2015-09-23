<?php

namespace Tests\Vivait\DelayedEventBundle;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\Event;
use Tests\Vivait\DelayedEventBundle\app\AppKernel;
use Tests\Vivait\DelayedEventBundle\Models\SimpleEntity;
use Vivait\DelayedEventBundle\Serializer\SerializerInterface;

/**
 * Checks that the serializer handles common cases
 */
class SerializerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ContainerInterface
     */
    private $container;

    function setUp() {
        $kernel = new AppKernel('test', true);
        $kernel->boot();

        AnnotationRegistry::registerAutoloadNamespace(
            'Tests\Vivait\DelayedEventBundle\Models', __DIR__ . '/Models/'
        );

        $this->container = $kernel->getContainer();
//
//        // Now, mock the repository so it returns the mock of the employee
//        $employeeRepository = $this->getMockBuilder('\Doctrine\ORM\EntityRepository')
//                                   ->disableOriginalConstructor()
//                                   ->getMock();
//        $employeeRepository->expects($this->once())
//                           ->method('find')
//                           ->will($this->returnValue($employee));
//
//        // Last, mock the EntityManager to return the mock of the repository
//        $entityManager = $this->getMockBuilder('\Doctrine\Common\Persistence\ObjectManager')
//                              ->disableOriginalConstructor()
//                              ->getMock();
//        $entityManager->expects($this->once())
//                      ->method('getRepository')
//                      ->will($this->returnValue($employeeRepository));
//
//        $this->container->re;
    }

    /**
     * @return SerializerInterface
     */
    private function getSerializer()
    {
        return $this->container->get('vivait_delayed_event.serializer');
    }

//    public function testBasicEvent()
//    {
//        $serialized = $this->getSerializer()->serialize(new Event());
//        var_dump($serialized);
//    }

    public function testEventWithEntity()
    {
        /** @var EntityManagerInterface $em */
        $em = $this->container->get('doctrine')->getManager();
        $entity = new SimpleEntity('Test');

        $em->persist($entity);

        $this->getSerializer()->serialize(new EntityEvent($entity));
    }
}

class EntityEvent extends Event {
    private $entity;

    function __construct($entity)
    {
        $this->entity = $entity;
    }

    /**
     * Gets entity
     * @return SimpleEntity
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * Sets entity
     * @param SimpleEntity $entity
     * @return $this
     */
    public function setEntity($entity)
    {
        $this->entity = $entity;

        return $this;
    }
}
