<?php

namespace Vivait\DelayedEventBundle;

use DateInterval;
use DateTime;
use Exception;

/**
 * Class IntervalCalculator
 * @package Vivait\DelayedEventBundle
 */
abstract class IntervalCalculator {
    /**
     * @param DateInterval $delay
     * @return int
     */
    public static function convertDateIntervalToSeconds(DateInterval $delay) {
        $now = new DateTime();
        $then = (new DateTime())->add($delay);

        return $then->getTimestamp() - $now->getTimestamp();
    }

    /**
     * Converts an interval specification or textual interval description to seconds
     * @param string|int|DateInterval $delay
     * @return DateInterval
     * @throws Exception
     */
    public static function convertDelayToInterval($delay)
    {
        if (!($delay instanceOf DateInterval)) {
            if (is_numeric($delay)) {
                $delay = 'PT'. (int)$delay .'S';
            }

            try {
                $delay = new DateInterval($delay);
            } catch (Exception $e) {
                $delay = DateInterval::createFromDateString($delay);
            }
        }

        if ($delay === null) {
            throw new Exception(sprintf('Could not parse interval representation "%s"', $delay));
        }

        return $delay;
    }
}
