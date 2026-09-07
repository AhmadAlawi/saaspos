<?php

namespace App\Exceptions;

use App\Models\Shift;
use RuntimeException;

/**
 * Thrown when a cashier tries to open a shift while one is already open
 * for them on the same store. The existing shift is exposed so the
 * controller can deep-link the user to its detail page.
 */
class ShiftAlreadyOpen extends RuntimeException
{
    public function __construct(public readonly Shift $existing)
    {
        parent::__construct(__('shifts.errors.already_open', [
            'number' => '#'.$existing->id,
        ]));
    }
}
