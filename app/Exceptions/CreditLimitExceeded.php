<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by CompleteSale / RecordCustomerPayment when the customer's
 * outstanding balance after this sale would exceed their configured
 * credit_limit. The cashier sees the customer's current outstanding +
 * the limit so they can take a deposit or refuse the credit.
 */
class CreditLimitExceeded extends RuntimeException
{
    public function __construct(
        public readonly string $customerName,
        public readonly string $currentBalance,
        public readonly string $newBalance,
        public readonly string $limit,
    ) {
        parent::__construct(__('sales.errors.credit_limit_exceeded', [
            'name'    => $customerName,
            'new'     => $newBalance,
            'limit'   => $limit,
        ]));
    }
}
