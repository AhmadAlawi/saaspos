<?php

namespace App\Exceptions;

use App\Models\Shift;
use RuntimeException;

/**
 * Thrown by {@see \App\Actions\Shifts\OpenShift} when another cashier
 * already has an open shift on the same terminal. One physical till =
 * one open drawer at a time (docs/features/cash-drawer-shifts.md §18.4);
 * the second cashier must take over via handover (close + reopen) rather
 * than run a parallel drawer on the same hardware.
 */
class TerminalShiftOpen extends RuntimeException
{
    public function __construct(public readonly Shift $existing)
    {
        parent::__construct(__('shifts.errors.terminal_busy'));
    }
}
