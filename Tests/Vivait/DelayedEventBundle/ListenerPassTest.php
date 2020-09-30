<?php

namespace Tests\Vivait\DelayedEventBundle;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Vivait\DelayedEventBundle\DependencyInjection\RegisterListenersPass;
use Vivait\DelayedEventBundle\DependencyInjection\VivaitDelayedEventExtension;
use Vivait\DelayedEventBundle\EventDispatcher\DelayedEventDispatcher;
use Vivait\DelayedEventBundle\Registry\DelayedEventsRegistry;
use Tests\Vivait\DelayedEventBundle\Mocks\TestSubscriber;

/**
 * Checks that the listener pass will take the tags from the container builder and register the listeners/subscribers
 */
class ListenerPassTest extends PHPUnit_Framework_TestCase
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
     * @return EventDispatcher
     */
    private function getDispatcher()
    {
        return $this->container->get('event_dispatcher');
    }

    /**
     * @return DelayedEventsRegistry
     */
    private function getDelayRegistry()
    {
        return $this->container->get('vivait_delayed_event.registry');
    }

    public function setUp() {
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

    public function testTriggerListener()
    {
        $this->container->register('test_listener', 'stdClass')
                        ->addTag('delayed_event.event_listener', [
                            'event' => 'test.event',
                            'method' => 'onMyEvent',
                            'delay' => '10'
                        ]);

        $this->container->register('test_listener2', 'stdClass')
                        ->addTag('delayed_event.event_listener', [
                            'event' => 'test.event',
                            'method' => 'onMyEvent',
                            'delay' => '10'
                        ]);

        $this->listenerPass->process($this->container);

        $dispatcher = $this->getDispatcher();
        PHPUnit_Framework_Assert::assertCount(1, $dispatcher->getListeners('test.event'));
    }

    public function testDuplicateDates()
    {
        $this->container->register('test_listener', 'stdClass')
                        ->addTag('delayed_event.event_listener', [
                            'event' => 'test.event',
                            'method' => 'onMyEvent',
                            'delay' => '1 day'
                        ]);

        $this->container->register('duplicate_test_listener', 'stdClass')
                        ->addTag('delayed_event.event_listener', [
                            'event' => 'test.event',
                            'method' => 'onMyEvent',
                            'delay' => '24 hours'
                        ]);

        $this->container->register('unique_test_listener', 'stdClass')
                        ->addTag('delayed_event.event_listener', [
                            'event' => 'test.event',
                            'method' => 'onMyEvent',
                            'delay' => '23 hours'
                        ]);

        $this->listenerPass->process($this->container);

        $registry = $this->getDelayRegistry();
        PHPUnit_Framework_Assert::assertCount(2, $registry->getDelays('test.event'));
    }

    public function testListenerWithoutMethod()
    {
        $this->container->register('test_listener', 'stdClass')
                        ->addTag('delayed_event.event_listener', [
                            'event' => 'test.event',
                            'delay' => '10'
                        ]);

        $this->listenerPass->process($this->container);

        $delayedEventName = $this->getDelayedEventName('test.event');

        $dispatcher = $this->getDispatcher();
        $listener = $dispatcher->getListeners($delayedEventName);

        // Check it auto-generated the listener method
        PHPUnit_Framework_Assert::assertSame('onTestEvent', $listener[0][1]);
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

        $delayedEventName = $this->getDelayedEventName('test.event');

        $dispatcher = $this->getDispatcher();
        $listener = $dispatcher->getListeners($delayedEventName);

        // Check the high priority event listener is first
        PHPUnit_Framework_Assert::assertCount(2, $listener);
        PHPUnit_Framework_Assert::assertSame('onHighPriorityEvent', $listener[0][1]);
    }

    public function testSubscriber()
    {
        $this->markTestIncomplete(
            'Subscriber support has not been implemented yet.'
        );

        $class = TestSubscriber::class;
        $id = 'test_subscriber';

        $this->container->register($id, $class)
                        ->addTag('delayed_event.event_subscriber', [
                            'delay' => 10,
                            'priority' => 5
                        ]);


        $this->listenerPass->process($this->container);

        $delayedEventName1 = $this->getDelayedEventName('test.event1');
        $delayedEventName2 = $this->getDelayedEventName('test.event2');

        $dispatcher = $this->getDispatcher();

        PHPUnit_Framework_Assert::assertCount(2, $dispatcher->getListeners($delayedEventName1));
        PHPUnit_Framework_Assert::assertCount(1, $dispatcher->getListeners($delayedEventName2));
    }

    /**
     * @param $eventName
     * @return string
     */
    private function getDelayedEventName($eventName)
    {
        $registry = $this->getDelayRegistry();

        $delayedEventName = $registry->getDelays($eventName);

        return key($delayedEventName);
    }
}
