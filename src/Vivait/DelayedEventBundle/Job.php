<?php

declare(strict_types=1);

namespace Vivait\DelayedEventBundle;

/**
 * Allow job ID or other metadata to be used in process.
 */
final class Job
{
    /**
     * The ID of the job currently being processed (null indicates no job).
     */
    public static ?string $id = null;
}
