<?php

namespace Tests\Vivait\DelayedEventBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Vivait\DelayedEventBundle\DependencyInjection\RegisterListenersPass;
use Vivait\DelayedEventBundle\DependencyInjection\VivaitDelayedEventExtension;
use Vivait\DelayedEventBundle\EventDispatcher\DelayedEventDispatcher;

class ListenerPassTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ContainerBuilder
     */
    private $container;

    /**
     * @var RegisterListenersPass
     */
    private $listenerPass;

    /**
     * @return DelayedEventDispatcher
     */
    private function getDispatcher()
    {
        return $this->container->get('delayed_event_dispatcher');
    }

    function setUp() {
        $this->container = new ContainerBuilder();
        $this->listenerPass = new RegisterListenersPass();

        $extension = new VivaitDelayedEventExtension();
        $extension->load([
            [
                'queue_transport' => 'memory'
            ]
        ], $this->container);

        // Register the event dispatcher service
        $this->container->setDefinition('event_dispatcher', new Definition(
            'Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher',
            array(new Reference('service_container'))
        ));
    }

    public function testListener()
    {
        $this->container->register('test_listener', 'stdClass')
                        ->addTag('delayed_event.event_listener', [
                            'event' => 'test.event',
                            'method' => 'onMyEvent',
                            'delay' => '10'
                        ]);

        $this->listenerPass->process($this->container);

        $dispatcher = $this->getDispatcher();
        \PHPUnit_Framework_Assert::assertTrue($dispatcher->hasListeners('test.event', 10));
    }

    public function testDateParsing()
    {
        $this->container->register('test_listener', 'stdClass')
                        ->addTag('delayed_event.event_listener', [
                            'event' => 'test.event',
                            'method' => 'onMyEvent',
                            'delay' => '1 day'
                        ]);

        $this->listenerPass->process($this->container);
        $dispatcher = $this->getDispatcher();

        // Test the various
        \PHPUnit_Framework_Assert::assertTrue($dispatcher->hasListeners('test.event', '1 day'));
        \PHPUnit_Framework_Assert::assertTrue($dispatcher->hasListeners('test.event', '24 hours'));
        \PHPUnit_Framework_Assert::assertTrue($dispatcher->hasListeners('test.event', '1440 minutes'));
        \PHPUnit_Framework_Assert::assertTrue($dispatcher->hasListeners('test.event', '86400 seconds'));
        \PHPUnit_Framework_Assert::assertTrue($dispatcher->hasListeners('test.event', 86400));
        \PHPUnit_Framework_Assert::assertTrue($dispatcher->hasListeners('test.event', new \DateInterval('P1D')));
        \PHPUnit_Framework_Assert::assertTrue($dispatcher->hasListeners('test.event', new \DateInterval('PT24H')));

        // Just to confirm it wasn't all a fluke
        \PHPUnit_Framework_Assert::assertFalse($dispatcher->hasListeners('test.event', 76400));
    }

    public function testListenerWithoutMethod()
    {
        $this->container->register('test_listener', 'stdClass')
                        ->addTag('delayed_event.event_listener', [
                            'event' => 'test.event',
                            'delay' => '10'
                        ]);


        $this->listenerPass->process($this->container);

        $dispatcher = $this->getDispatcher();
        $listener = $dispatcher->getListeners('test.event', 10);

        // Check it auto-generated the listener method
        \PHPUnit_Framework_Assert::assertSame('onTestEvent', $listener[0][1]);
    }

    public function testListenerWithPriority()
    {
        $this->container->register('test_listener', 'stdClass')
                        ->addTag('delayed_event.event_listener', [
                            'event' => 'test.event',
                            'delay' => 15,
                            'priority' => 5
                        ]);

        $this->container->register('higher_priority_listener', 'stdClass')
                        ->addTag('delayed_event.event_listener', [
                            'event' => 'test.event',
                            'method' => 'onHighPriorityEvent',
                            'delay' => 15,
                            'priority' => 10
                        ]);

        $this->listenerPass->process($this->container);

        $dispatcher = $this->getDispatcher();
        $listener = $dispatcher->getListeners('test.event', 15);

        // Check the high priority event listener is first
        \PHPUnit_Framework_Assert::assertCount(2, $listener);
        \PHPUnit_Framework_Assert::assertSame('onHighPriorityEvent', $listener[0][1]);
    }

    public function testSubscriber()
    {
        $class = 'Tests\Vivait\DelayedEventBundle\Mocks\TestSubscriber';
        $id = 'test_subscriber';

        $this->container->register($id, $class)
                        ->addTag('delayed_event.event_subscriber', [
                            'delay' => 10,
                            'priority' => 5
                        ]);


        $this->listenerPass->process($this->container);

        $dispatcher = $this->getDispatcher();

        \PHPUnit_Framework_Assert::assertCount(2, $dispatcher->getListeners('test.event1', 10));
        \PHPUnit_Framework_Assert::assertCount(1, $dispatcher->getListeners('test.event2', 5));
    }
}
