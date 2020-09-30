<?php

namespace Tests\Vivait\DelayedEventBundle\Mocks;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class TestSubscriber
 * @package Tests\Vivait\DelayedEventBundle\Mocks
 */
class TestSubscriber implements EventSubscriberInterface
{
    /**
     * @return array
     */
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

    /**
     * @param $args
     */
    public function anotherTestEvent($args)
    {
        // Do nothing
    }

    /**
     * @param $args
     */
    public function testEvent($args)
    {
        // Do nothing
    }
}
