<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see App\Actions\Products\DeleteProduct} when the product
 * has been sold at least once. We refuse to delete because removing a
 * product that's referenced from `sale_items` would orphan historical
 * sales reports and corrupt the audit trail.
 *
 * The operator should either archive the product (`is_active = false`)
 * or leave it as-is. Soft-delete is reserved for never-sold mistakes.
 */
class ProductHasSales extends RuntimeException
{
    public function __construct(public readonly int $count)
    {
        parent::__construct("Cannot delete product — {$count} sale line(s) reference it.");
    }
}
