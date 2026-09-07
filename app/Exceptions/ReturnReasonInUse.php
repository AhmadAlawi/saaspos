<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by the return-reasons delete endpoint when the reason has
 * already been used on at least one `sale_returns` row. We keep
 * historical reasons readable on past refund receipts; the operator
 * can deactivate the row instead.
 */
class ReturnReasonInUse extends RuntimeException
{
    public function __construct(public readonly int $count)
    {
        parent::__construct('Return reason is in use by '.$count.' refund(s).');
    }
}
