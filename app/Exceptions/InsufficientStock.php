<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see App\Actions\Sales\CompleteSale} when a sale line wants
 * more units than the store has on hand and the negative-stock policy
 * blocks it (Slice 1 default).
 */
class InsufficientStock extends RuntimeException
{
    public function __construct(
        public readonly string $productName,
        public readonly string $available,
        public readonly string $requested,
    ) {
        parent::__construct(
            "Insufficient stock for \"{$productName}\": requested {$requested}, available {$available}."
        );
    }
}
