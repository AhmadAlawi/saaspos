<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by CompleteSale when a checkout tries to redeem more points
 * than the attached customer actually has. Mirrors CreditLimitExceeded's
 * shape — carries enough context for the cashier UI to show a useful
 * message without a second round-trip.
 */
class InsufficientLoyaltyPoints extends RuntimeException
{
    public function __construct(
        public readonly string $customerName,
        public readonly int $available,
        public readonly int $requested,
    ) {
        parent::__construct(__('sales.errors.insufficient_loyalty_points', [
            'name'      => $customerName,
            'available' => $available,
            'requested' => $requested,
        ]));
    }
}
