<?php

namespace Tests\Vivait\DelayedEventBundle\Mocks;

use Exception;

/**
 * Class TestExceptionListener
 * @package Tests\Vivait\DelayedEventBundle\Mocks
 */
class TestExceptionListener
{
    /**
     * @var int
     */
    public static $attempt = 0;

    /**
     * @var bool
     */
    public static $succeeded = false;

    /**
     * @param mixed $args
     * 
     * @throws Exception
     */
    public function onListenEvent($args)
    {
        $throw = false;
        
        if (self::$attempt < 3) {
            $throw = true;
        }
        
        self::$attempt++;
        
        if ($throw) {
            throw new Exception();
        }
        
        self::$succeeded = true;
    }

    /**
     * Reset
     */
    public static function reset() {
        self::$attempt = 0;
        self::$succeeded = false;
    }
}
