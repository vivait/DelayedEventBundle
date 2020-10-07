<?php

namespace Vivait\DelayedEventBundle\Exception;

use Exception;
use Throwable;

/**
 * Class TerminalEventException
 * @package Vivait\DelayedEventBundle\Exception
 */
class TerminalEventException extends Exception
{
    public static function because(Throwable $throwable): Exception
    {
        return new self($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
    }
}