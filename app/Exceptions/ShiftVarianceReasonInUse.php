<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a variance reason can't be deleted because past shifts
 * still reference it. The operator can deactivate it instead — closed
 * shifts then keep the historical reason label intact.
 */
class ShiftVarianceReasonInUse extends RuntimeException
{
    public function __construct(public readonly int $count)
    {
        parent::__construct('Variance reason is in use by '.$count.' shift(s).');
    }
}
