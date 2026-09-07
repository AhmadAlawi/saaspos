<?php

namespace App\Exceptions;

use App\Models\TradingDay;
use RuntimeException;

/**
 * Thrown when "Close Day" is attempted but the trading day is already
 * closed, or still has an open employee shift under it.
 */
class TradingDayNotClosable extends RuntimeException
{
    public function __construct(public readonly TradingDay $day, string $reasonKey)
    {
        parent::__construct(__('shifts.day_errors.'.$reasonKey));
    }
}
