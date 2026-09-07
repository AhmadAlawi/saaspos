<?php

namespace App\Exceptions;

use App\Models\Sale;
use RuntimeException;

/**
 * Thrown by ChangeSalePaymentMethod when the sale or the requested new
 * method disqualifies the change. Common causes:
 *   - the sale is voided (void is final — use a fresh sale instead)
 *   - the requested new payment method is inactive
 *   - the requested new payment method is the same as the current one
 */
class SalePaymentNotEditable extends RuntimeException
{
    public function __construct(
        public readonly Sale $sale,
        public readonly string $reason,
    ) {
        parent::__construct(__('sales.errors.payment_not_editable.'.$reason));
    }
}
