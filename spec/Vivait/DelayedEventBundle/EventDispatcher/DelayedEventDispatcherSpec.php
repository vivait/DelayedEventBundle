<?php

namespace spec\Vivait\DelayedEventBundle\EventDispatcher;

use Doctrine\Common\EventSubscriber;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Vivait\DelayedEventBundle\EventDispatcher\DelayedEventDispatcher;
use Vivait\DelayedEventBundle\Queue\QueueInterface;
use Vivait\DelayedEventBundle\Serializer\SerializerInterface;

/**
 * @mixin DelayedEventDispatcher
 */
class DelayedEventDispatcherSpec extends ObjectBehavior
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    function let(SerializerInterface $serializer, QueueInterface $queue) {
        $this->dispatcher = new EventDispatcher();
        $this->beConstructedWith($serializer, $queue, $this->dispatcher);
    }

    function it_should_add_to_the_queue_when_an_event_is_triggered(QueueInterface $queue) {
        $delay = 10;
        $eventName = 'test.event';
        $delayedEventName = $this->generateDelayedEventName($eventName, $delay);

        $this->addListener($eventName, 'echo', $delay);
        $this->dispatcher->dispatch($eventName, new Event);

        $queue->put($delayedEventName, null, $delay)->shouldHaveBeenCalled();
    }

    function it_should_add_to_the_queue_when_an_event_is_triggered_via_a_subscriber(QueueInterface $queue) {
        $this->addSubscriber(new TestSubscriber());

        $delay = 10;
        $eventName = 'test.event1';
        $delayedEventName = $this->generateDelayedEventName($eventName, $delay);

        \PHPUnit_Framework_Assert::assertCount(2, $this->dispatcher->getListeners($delayedEventName->getWrappedObject()));
        $queue->put($delayedEventName, null, $delay)->shouldBeCalled();
        $this->dispatcher->dispatch($eventName, new Event);

        $delay = 5;
        $eventName = 'test.event2';
        $delayedEventName = $this->generateDelayedEventName($eventName, $delay);

        \PHPUnit_Framework_Assert::assertCount(1, $this->dispatcher->getListeners($delayedEventName->getWrappedObject()));
        $queue->put($delayedEventName, null, $delay)->shouldBeCalled();
        $this->dispatcher->dispatch($eventName, new Event);
    }

    function it_should_only_register_only_one_listener_trigger(SerializerInterface $serializer, QueueInterface $queue, EventDispatcher $dispatcher) {
        // Convert the dispatcher in to a spy
        $this->beConstructedWith($serializer, $queue, $dispatcher);

        $delay = 10;
        $eventName = 'test.event1';
        $delayedEventName = $this->generateDelayedEventName($eventName, $delay);

        // Add a couple of listeners to the same event & delay
        $this->addListener($eventName, 'echo', $delay);
        $this->addListener($eventName, 'echo', $delay);

        // Check that the trigger listener was only added once
        $dispatcher->addListener($eventName, Argument::cetera())
                   ->shouldHaveBeenCalledTimes(1);

        // Check both the delayed events were added still
        $dispatcher->addListener($delayedEventName, 'echo', Argument::any())
            ->shouldHaveBeenCalledTimes(2);
    }

    function it_should_serialize_an_event(QueueInterface $queue, SerializerInterface $serializer) {
        $delay = 5;
        $eventName = 'test.event2';
        $event = new ComplexEvent;
        $delayedEventName = $this->generateDelayedEventName($eventName, $delay);

        $serializer->serialize($event)
                   ->willReturn(serialize('fake serialization'))
                   ->shouldBeCalled();

        $queue->put($delayedEventName, Argument::type('string'), $delay)
            ->shouldBeCalled();

        $this->addListener($eventName, 'echo', $delay);
        $this->dispatcher->dispatch($eventName, $event);
    }

    function it_should_remove_the_trigger_listener_when_removing_last_listener() {
        $eventName = 'test.event3';
        $delay = 5;

        $this->addListener($eventName, 'echo', $delay);
        $this->addListener($eventName, 'printf', $delay);

        // Check the trigger listener was NOT removed YET
        \PHPUnit_Framework_Assert::assertTrue($this->dispatcher->hasListeners($eventName), 'Trigger listener removed when listeners still exist');

        $this->removeListener($eventName, 'echo', $delay);

        // Check the listener was removed
        $this->getListeners($eventName)->shouldHaveCount(1);

        $this->removeListener($eventName, 'printf', $delay);

        // Check the trigger listener was removed
        \PHPUnit_Framework_Assert::assertFalse($this->dispatcher->hasListeners($eventName), 'Trigger listener not removed');

        // Check all listeners have been removed
        $this->shouldNotHaveListeners($eventName, $delay);
    }
}

class TestSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'test.event1' => [
                ['testEvent', 10],
                ['anotherTest', 10, 1]
            ],
            'test.event2' => ['testEvent', 5],
        );
    }

    public function anotherTestEvent($args)
    {
        // Do nothing
    }

    public function testEvent($args)
    {
        // Do nothing
    }
}

class ComplexEvent extends Event {
    private $variable = 'test';
}
