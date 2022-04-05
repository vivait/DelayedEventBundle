<?php

declare(strict_types=1);

namespace Tests\Vivait\DelayedEventBundle\Mocks;

use Exception;

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
     * @throws Exception
     */
    public function onListenEvent(): void
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

    public static function reset(): void
    {
        self::$attempt = 0;
        self::$succeeded = false;
    }
}
