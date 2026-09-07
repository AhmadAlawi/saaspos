<?php

namespace App\Actions\Suppliers;

use App\Exceptions\SupplierHasBalance;
use App\Exceptions\SupplierHasOpenPurchases;
use App\Models\Purchase;
use App\Models\Supplier;

/**
 * Soft-delete a supplier. Blocks when there's still money owed — a
 * non-zero outstanding payable. The UI surfaces the block and offers
 * deactivation as an alternative (preserves history).
 *
 * Hooks:
 *   - action `supplier.before_delete` ($supplier)
 *   - action `supplier.after_delete`  ($supplier)
 */
class DeleteSupplier
{
    public function __invoke(Supplier $supplier): void
    {
        if ((float) $supplier->outstanding_balance != 0.0) {
            throw new SupplierHasBalance((float) $supplier->outstanding_balance);
        }

        // Open purchases block deletion too. A supplier with draft /
        // received / partially_paid POs against their name is still in
        // an active business relationship; deleting them would orphan
        // the open documents.
        $openCount = Purchase::query()
            ->where('supplier_id', $supplier->getKey())
            ->whereIn('status', [
                Purchase::STATUS_DRAFT,
                Purchase::STATUS_SUBMITTED,
                Purchase::STATUS_RECEIVED,
                Purchase::STATUS_PARTIALLY_PAID,
            ])
            ->count();
        if ($openCount > 0) {
            throw new SupplierHasOpenPurchases($openCount);
        }

        do_action('supplier.before_delete', $supplier);

        $supplier->delete();

        do_action('supplier.after_delete', $supplier);
    }
}
