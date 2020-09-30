<?php

namespace Tests\Vivait\DelayedEventBundle\Mocks;

/**
 * Class TestListener
 * @package Tests\Vivait\DelayedEventBundle\Mocks
 */
class TestListener
{
    public static $hasRan = false;

    /**
     * @param $args
     */
    public function onListenEvent($args)
    {
        self::$hasRan = true;
    }

    public static function reset() {
        self::$hasRan = false;
    }
}
