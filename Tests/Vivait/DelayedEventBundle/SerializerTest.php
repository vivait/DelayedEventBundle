<?php

namespace Tests\Vivait\DelayedEventBundle;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\Event;
use Tests\Vivait\DelayedEventBundle\app\AppKernel;
use Tests\Vivait\DelayedEventBundle\Models\SimpleEntity;
use Vivait\DelayedEventBundle\Queue\Job;
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

    public function testBasicEvent()
    {
        $serialized = $this->getSerializer()->serialize(new SimpleEvent('Test'));

        var_dump($serialized);
    }

//    public function testEventWithEntity()
//    {
//        /** @var EntityManagerInterface $em */
//        $em = $this->container->get('doctrine')->getManager();
//        $entity = new SimpleEntity('Test', 1);
//
//        $em->persist($entity);
//
//        $serialized = $this->getSerializer()->serialize(new SimpleEvent($entity));
//        \PHPUnit_Framework_Assert::assertInternalType('string', $serialized);
//
//        var_dump($serialized);
//        $deserialized = $this->getSerializer()->deserialize($serialized);
//
//        var_dump($deserialized);
//    }
}

class SimpleEvent extends Event {
    private $test;

    function __construct($test)
    {
        $this->test = $test;
    }

    public function getTest()
    {
        return $this->test;
    }

    public function setTest($test)
    {
        $this->test = $test;

        return $this;
    }
}
