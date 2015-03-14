<?php

namespace Tests\Vivait\DelayedEventBundle\Mocks;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
