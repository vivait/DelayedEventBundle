<?php

namespace Vivait\DelayedEventBundle\Exception;

use Exception;
use Throwable;

/**
 * Class TerminalEventException
 * @package Vivait\DelayedEventBundle\Exception
 *
 * A terminal event exception should be thrown if the event fails for a know 'hard' reason as an example
 * if a credit search failes with a 'no-match', no matter how many retries, it will always fail with this.
 *
 * This will ignore any retry handling and immediately hard fail.
 */
class TerminalEventException extends Exception
{
    public static function because(Throwable $throwable): Exception
    {
        return new self($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
    }
}
