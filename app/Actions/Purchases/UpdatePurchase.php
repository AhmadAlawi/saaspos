<?php

namespace App\Actions\Purchases;

use App\Exceptions\PurchaseNotEditable;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Update a DRAFT purchase. Items are replaced wholesale — simpler and
 * more reliable than per-row diff for a typically small line list.
 * Refuses to edit anything past `draft` status — those go through
 * dedicated actions (Receive, RecordPayment, Cancel) in later slices.
 *
 * Hooks:
 *   - filter `purchase.fillable`     → adjust the header array
 *   - action `purchase.before_update`→ ($purchase, $header, $lines)
 *   - action `purchase.after_update` → ($purchase)
 *
 * @param array<string, mixed>            $header
 * @param array<int, array<string, mixed>> $lines
 */
class UpdatePurchase
{
    public function __construct(private ComputePurchaseTotals $computeTotals) {}

    public function __invoke(Purchase $purchase, array $header, array $lines, ?User $updater = null): Purchase
    {
        if ($purchase->status !== Purchase::STATUS_DRAFT) {
            throw new PurchaseNotEditable($purchase->status);
        }

        $header = apply_filters('purchase.fillable', $header, $purchase);

        // These columns are computed or owned by other actions — never
        // accept them from a draft edit.
        unset(
            $header['status'],
            $header['number'],
            $header['paid_total'],
            $header['balance_due'],
            $header['is_received_in_full'],
        );

        if ($updater) {
            $header['updated_by'] = $updater->id;
        }

        do_action('purchase.before_update', $purchase, $header, $lines);

        return DB::transaction(function () use ($purchase, $header, $lines) {
            [$normalLines, $totals] = ($this->computeTotals)($lines);

            $header = array_merge($header, $totals);
            // `forceFill()->save()` so the computed totals (subtotal,
            // discount_total, tax_total, grand_total) — which are
            // non-fillable on the model — actually persist. See
            // Purchase::$fillable for the rationale.
            $purchase->forceFill($header)->save();

            $purchase->items()->delete();
            $purchase->items()->createMany($normalLines);

            do_action('purchase.after_update', $purchase);

            return $purchase->refresh();
        });
    }
}
