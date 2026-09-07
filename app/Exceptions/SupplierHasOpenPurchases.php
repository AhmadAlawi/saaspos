<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see App\Actions\Suppliers\DeleteSupplier} and the
 * toggle-active controller action when a supplier still has at
 * least one purchase in draft / submitted / received / partially_paid
 * status. Close those POs first (cancel or pay) before retiring
 * the supplier.
 */
class SupplierHasOpenPurchases extends RuntimeException
{
    public function __construct(public readonly int $count)
    {
        parent::__construct("Supplier has {$count} open purchase(s).");
    }
}
