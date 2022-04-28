<?php

namespace Tests\Vivait\DelayedEventBundle;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Vivait\DelayedEventBundle\DependencyInjection\RegisterListenersPass;
use Vivait\DelayedEventBundle\DependencyInjection\VivaitDelayedEventExtension;
use Vivait\DelayedEventBundle\Registry\DelayedEventsRegistry;

/**
 * Checks that the listener pass will register events with the base event dispatcher when disabled
 */
class DisabledListenerPassTest extends TestCase
{
    private ContainerBuilder $container;
    private RegisterListenersPass $listenerPass;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->listenerPass = new RegisterListenersPass();

        $this->container->setParameter('kernel.environment', 'test');

        $extension = new VivaitDelayedEventExtension();
        $extension->load(
            [
                [
                    'queue_transport' => 'memory',
                    'enabled' => false,
                ],
            ],
            $this->container,
        );

        // Register the event dispatcher service
        $this->container->setDefinition(
            'event_dispatcher',
            new Definition(EventDispatcher::class),
        );
    }

    public function testTriggerListener(): void
    {
        $this->container
            ->register('test_listener', 'stdClass')
            ->addTag(
                'delayed_event.event_listener',
                [
                    'event' => 'test.event',
                    'method' => 'onMyEvent',
                    'delay' => '10',
                ],
            )
        ;

        $this->container
            ->register('test_listener2', 'stdClass')
            ->addTag(
                'delayed_event.event_listener',
                [
                    'event' => 'test.event',
                    'method' => 'onMyEvent',
                    'delay' => '10',
                ],
            )
        ;

        $this->listenerPass->process($this->container);

        $dispatcher = $this->getDispatcher();
        Assert::assertCount(2, $dispatcher->getListeners('test.event'));
    }

    public function testListenerWithoutMethod()
    {
        $this->container
            ->register('test_listener', 'stdClass')
            ->addTag(
                'delayed_event.event_listener',
                [
                    'event' => 'test.event',
                    'delay' => '10',
                ],
            )
        ;

        $this->listenerPass->process($this->container);

        $dispatcher = $this->getDispatcher();
        $listener = $dispatcher->getListeners('test.event');

        // Check it auto-generated the listener method
        Assert::assertSame('onTestEvent', $listener[0][1]);
    }

    public function testListenerWithPriority()
    {
        $this->container
            ->register('test_listener', 'stdClass')
            ->addTag(
                'delayed_event.event_listener',
                [
                    'event' => 'test.event',
                    'delay' => 15,
                    'priority' => 5,
                ],
            )
        ;

        $this->container
            ->register('higher_priority_listener', 'stdClass')
            ->addTag(
                'delayed_event.event_listener',
                [
                    'event' => 'test.event',
                    'method' => 'onHighPriorityEvent',
                    'delay' => 15,
                    'priority' => 10,
                ],
            )
        ;

        $this->listenerPass->process($this->container);

        $dispatcher = $this->getDispatcher();
        $listener = $dispatcher->getListeners('test.event');

        // Check the high priority event listener is first
        Assert::assertCount(2, $listener);
        Assert::assertSame('onHighPriorityEvent', $listener[0][1]);
    }

    private function getDispatcher(): EventDispatcherInterface
    {
        return $this->container->get('event_dispatcher');
    }

    private function getDelayRegistry(): DelayedEventsRegistry
    {
        return $this->container->get('vivait_delayed_event.registry');
    }
}
