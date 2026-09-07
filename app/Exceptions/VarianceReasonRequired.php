<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by CloseShift when |variance| exceeds the configured tolerance
 * and the caller didn't supply a `variance_reason`. The controller
 * catches this to flip the close form into "require reason" mode.
 */
class VarianceReasonRequired extends RuntimeException
{
    public function __construct(
        public readonly string $variance,
        public readonly string $tolerance,
    ) {
        parent::__construct(__('shifts.errors.variance_reason_required'));
    }
}
