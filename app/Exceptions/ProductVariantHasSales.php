<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when deleting a {@see App\Models\ProductVariant} that has at
 * least one row in `sale_items.variant_id`. Removing the variant
 * would orphan historical sale lines, breaking reporting.
 *
 * `$label` carries the variant's display label so the user-facing
 * message can name the exact SKU that's blocking the action.
 */
class ProductVariantHasSales extends RuntimeException
{
    public function __construct(
        public readonly int $count,
        public readonly string $label = '',
    ) {
        parent::__construct("Cannot delete variant — {$count} sale line(s) reference it.");
    }
}
