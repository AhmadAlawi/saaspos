<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see App\Actions\Suppliers\DeleteSupplier} when a
 * supplier still has a non-zero outstanding payable. Caught by the
 * controller and surfaced as a friendly flash so the user can settle
 * the balance first, or deactivate the supplier (preserves history).
 *
 * Mirrors {@see CustomerHasBalance} for symmetry.
 */
class SupplierHasBalance extends RuntimeException
{
    public function __construct(public readonly float $amount)
    {
        parent::__construct("Supplier has a non-zero outstanding balance of {$amount}.");
    }
}
