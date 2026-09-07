<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see \App\Actions\Shifts\OpenShift} when the store requires
 * an explicitly-opened trading day (`stores.require_day_open`) and no
 * open {@see \App\Models\TradingDay} exists yet for this store/terminal
 * today — the day auto-create path is skipped entirely in that mode.
 */
class TradingDayNotOpen extends RuntimeException
{
    public function __construct(public readonly int $storeId, public readonly ?int $terminalId)
    {
        parent::__construct(__('shifts.day_errors.not_open'));
    }
}
