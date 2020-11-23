<?php

namespace Vivait\DelayedEventBundle\Exception;

use Exception;
use Throwable;

/**
 * Class TransientEventException
 * @package Vivait\DelayedEventBundle\Exception
 *
 * A transient event exception should be thrown if the event fails for a know 'soft' reason as an example
 * if an OpenBanking lookup returns an empty 204 because it isn't ready and it is expected to be retried.
 *
 * This will then be retried in-line with the usual retry policy.
 *
 * The key difference between a regular exception and a transient one is in logging, where a regular exception
 * (i.e. Guzzle) will be logged as a transient internal system fault (database not ready, timeouts on the load balancer,
 * etc) which should always trigger an error. A transient exception is with the third-party and we're expecting them to
 * resolve the issue within the retries, therefore there is no point logging as an error, and instead log a warning.
 *
 * If this hard fails because there are no further retries, then an error will be thrown.
 */
class TransientEventException extends Exception
{
    public static function because(Throwable $throwable): Exception
    {
        return new self($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
    }
}
