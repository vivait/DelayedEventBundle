<?php

namespace Tests\Vivait\DelayedEventBundle\Mocks;

/**
 * Class TestListener
 * @package Tests\Vivait\DelayedEventBundle\Mocks
 */
class TestListener
{
    public static $hasRan = false;
    private $flagFile;

    /**
     * @param string $flagFile
     */
    public function __construct($flagFile)
    {
        if (!$flagFile) {
            throw new \InvalidArgumentException('Flag file must be specified');
        }

        @unlink($flagFile);
        $this->flagFile = $flagFile;
    }

    /**
     * @param $args
     */
    public function onListenEvent($args)
    {
        self::$hasRan = true;

        touch($this->flagFile);
    }

    public static function reset() {
        self::$hasRan = false;
    }
}
