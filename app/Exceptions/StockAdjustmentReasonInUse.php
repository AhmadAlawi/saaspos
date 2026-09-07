<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see App\Actions\StockAdjustmentReasons\DeleteStockAdjustmentReason}
 * when at least one stock_adjustments row still references the reason.
 * Caught by the controller and surfaced as a friendly flash so the user
 * can either reassign or deactivate instead.
 */
class StockAdjustmentReasonInUse extends RuntimeException
{
    public function __construct(public readonly int $count)
    {
        parent::__construct("Reason is used by {$count} adjustment(s).");
    }
}
