<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see \App\Actions\Sales\CompleteSale} when a sale line
 * references an expired `product_batch` AND the company has
 * `block_expired_batch_sale=true` AND the cashier does NOT hold the
 * `inventory.sell_expired` override permission.
 *
 * Pharmacy shops typically want a hard block at the cashier and a
 * manager-override path for the legitimate "return-to-vendor" flow.
 */
class ExpiredBatchSale extends RuntimeException
{
    public function __construct(
        public readonly string $productName,
        public readonly string $batchNumber,
        public readonly string $expiryDate,
    ) {
        parent::__construct(
            "Cannot sell expired batch \"{$batchNumber}\" of \"{$productName}\" (expired {$expiryDate})."
        );
    }
}
