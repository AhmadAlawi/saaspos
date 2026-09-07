<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see \App\Actions\Sales\CompleteSale} when the effective
 * discount exceeds the store's `discount_threshold_percent` and the user
 * lacks `sales.discount_above_threshold` (and no manager approval was
 * supplied — manager approval lands in Slice 2).
 */
class DiscountAboveThreshold extends RuntimeException
{
    public function __construct(
        public readonly string $effectivePercent,
        public readonly string $thresholdPercent,
    ) {
        parent::__construct(__('sales.errors.discount_above_threshold', [
            'percent'   => rtrim(rtrim($effectivePercent, '0'), '.'),
            'threshold' => rtrim(rtrim($thresholdPercent, '0'), '.'),
        ]));
    }
}
