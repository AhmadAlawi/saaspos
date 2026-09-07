<?php

namespace App\Actions\Purchases;

use App\Exceptions\PurchaseNotEditable;
use App\Models\Purchase;

/**
 * Soft-delete a DRAFT purchase. Anything past draft must go through
 * the Cancel flow (Slice 3+) which reverses stock + journal first.
 *
 * Hooks:
 *   - action `purchase.before_delete` ($purchase)
 *   - action `purchase.after_delete`  ($purchase)
 */
class DeletePurchase
{
    public function __invoke(Purchase $purchase): void
    {
        if ($purchase->status !== Purchase::STATUS_DRAFT) {
            throw new PurchaseNotEditable($purchase->status);
        }

        do_action('purchase.before_delete', $purchase);

        $purchase->delete();

        do_action('purchase.after_delete', $purchase);
    }
}
