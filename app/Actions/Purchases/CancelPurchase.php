<?php

namespace App\Actions\Purchases;

use App\Exceptions\PurchaseNotEditable;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a purchase. Behavior depends on the source state:
 *
 *   - `draft` / `submitted` → status flips to `cancelled`. No stock,
 *     no journal, no supplier-balance change. The supplier never saw
 *     this PO so cancellation is a clean no-op except for the audit
 *     trail.
 *   - `received` / `partially_paid` / `paid` → REFUSED. The user is
 *     told to use the Purchase Return flow (Slice 5) instead, which
 *     reverses stock + journal + payments atomically.
 *
 * Concurrency: wrapped in `DB::transaction` + `lockForUpdate()` on the
 * purchase row with an in-transaction status re-check. This blocks
 * the prior race where User A's Receive locks the row, User B's Cancel
 * reads stale `status='draft'`, waits at the next mutation, then flips
 * status='cancelled' AFTER Receive committed — leaving stock incremented
 * and supplier balance bumped on a "cancelled" PO. With the lock,
 * Cancel waits for Receive to commit, then sees `status='received'`
 * and refuses correctly.
 *
 * Hooks: `purchase.before_cancel`, `purchase.after_cancel`.
 */
class CancelPurchase
{
    public function __invoke(Purchase $purchase, ?User $canceller = null, ?string $reason = null): Purchase
    {
        // Fast-fail outside the txn for the common stale-page case
        // (cheap read, doesn't take a lock when it's obviously not
        // editable).
        if (! \in_array($purchase->status, [Purchase::STATUS_DRAFT, Purchase::STATUS_SUBMITTED], true)) {
            throw new PurchaseNotEditable($purchase->status);
        }

        do_action('purchase.before_cancel', $purchase, $reason);

        return DB::transaction(function () use ($purchase, $canceller, $reason) {
            $locked = Purchase::query()
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->first();

            // Re-check inside the lock — this is what blocks the
            // Receive-vs-Cancel race. If a concurrent Receive committed
            // while we were waiting on the lock, status is now
            // 'received' and we must refuse.
            if (! $locked || ! \in_array($locked->status, [Purchase::STATUS_DRAFT, Purchase::STATUS_SUBMITTED], true)) {
                throw new PurchaseNotEditable($locked?->status ?? 'unknown');
            }
            $purchase = $locked;

            // `forceFill()->save()` — `status` is non-fillable on the
            // model (transition actions own it). `notes` is fillable
            // but goes through the same path for consistency.
            $purchase->forceFill([
                'status'     => Purchase::STATUS_CANCELLED,
                'updated_by' => $canceller?->id,
                // The reason text is appended to notes so it survives the
                // cancellation. A dedicated `cancellation_reason` column
                // can land in the schema-revisions slice if reporting needs
                // it broken out.
                'notes'      => trim(($purchase->notes ? $purchase->notes."\n\n" : '')
                                      .($reason ? "[Cancelled] {$reason}" : '[Cancelled]')),
            ])->save();

            do_action('purchase.after_cancel', $purchase);

            return $purchase->refresh();
        });
    }
}
