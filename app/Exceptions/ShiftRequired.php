<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see \App\Actions\Sales\CompleteSale} when the store has
 * `enforce_shifts = true`, the cashier has no open shift, and they do
 * NOT hold the `shifts.bypass_enforcement` override permission.
 *
 * The cashier screen gates selling behind an open-shift modal so this
 * rarely surfaces interactively; it is the server-side backstop and the
 * guard for offline-synced sales that land after their shift closed.
 */
class ShiftRequired extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('shifts.errors.shift_required'));
    }
}
