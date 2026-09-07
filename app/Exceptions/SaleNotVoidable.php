<?php

namespace App\Exceptions;

use App\Models\Sale;
use RuntimeException;

/**
 * Thrown by VoidSale when the sale's current state disqualifies it
 * from being voided. Common causes:
 *   - status is not `completed` (held / draft / already voided / refunded)
 *   - the sale has one or more refunds (use the refund flow instead)
 *   - the sale's shift is closed (same-shift-only policy in Slice 1)
 */
class SaleNotVoidable extends RuntimeException
{
    public function __construct(
        public readonly Sale $sale,
        public readonly string $reason,
    ) {
        parent::__construct(__('sales.errors.not_voidable.'.$reason));
    }
}
