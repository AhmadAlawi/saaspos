<?php

namespace App\Exceptions;

use App\Models\Shift;
use RuntimeException;

/**
 * Thrown when an action is attempted against a shift that isn't in the
 * `open` state (e.g., close, pay-in, pay-out on an already-closed shift).
 */
class ShiftNotOpen extends RuntimeException
{
    public function __construct(public readonly Shift $shift)
    {
        parent::__construct(__('shifts.errors.not_open'));
    }
}
