<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see App\Actions\Customers\DeleteCustomer} when a customer
 * with non-zero outstanding balance or store credit is queued for
 * delete. Caught by the controller and surfaced as a friendly flash so
 * the user can deactivate (preserves history) or settle the balance
 * before deleting.
 */
class CustomerHasBalance extends RuntimeException
{
    public function __construct(public readonly string $kind, public readonly float $amount)
    {
        parent::__construct("Customer has a non-zero {$kind} of {$amount}.");
    }
}
