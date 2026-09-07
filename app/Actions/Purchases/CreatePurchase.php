<?php

namespace App\Actions\Purchases;

use App\Models\Purchase;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Persist a new purchase as a DRAFT — no stock movements, no journal
 * entries, no supplier-balance changes. Those land in the Receive
 * slice. This slice just captures what the user types into the form.
 *
 * `paid_total` / `balance_due` stay at 0 — payments arrive separately.
 * `status` is forced to draft regardless of what's posted.
 *
 * Hooks:
 *   - filter `purchase.fillable`     → adjust the header array
 *   - action `purchase.before_create`→ ($header, $lines)
 *   - action `purchase.after_create` → ($purchase)
 *
 * @param array<string, mixed>            $header
 * @param array<int, array<string, mixed>> $lines
 */
class CreatePurchase
{
    public function __construct(
        private GeneratePurchaseNumber $generateNumber,
        private ComputePurchaseTotals  $computeTotals,
    ) {}

    public function __invoke(array $header, array $lines, ?User $creator = null): Purchase
    {
        $header = apply_filters('purchase.fillable', $header);
        $header['status']              = Purchase::STATUS_DRAFT;
        $header['is_received_in_full'] = false;
        $header['paid_total']          = '0';
        $header['balance_due']         = '0';

        if ($creator) {
            $header['created_by'] = $creator->id;
            $header['updated_by'] = $creator->id;
        }

        do_action('purchase.before_create', $header, $lines);

        return DB::transaction(function () use ($header, $lines) {
            // Locked for the transaction — serializes concurrent purchase
            // creations for the same store so GeneratePurchaseNumber's
            // unlocked MAX(number) read can't race (same fix as the
            // analogous sale-number race in CompleteSale.php).
            Store::query()->where('id', (int) $header['store_id'])->lockForUpdate()->firstOrFail();

            [$normalLines, $totals] = ($this->computeTotals)($lines);

            $header = array_merge($header, $totals);
            $header['number'] = ($this->generateNumber)((int) $header['store_id']);

            // `forceFill()->save()` because most computed columns
            // (status, totals, paid/balance, number) are deliberately
            // non-fillable on the model — see Purchase::$fillable. The
            // Action is the trusted writer; `Purchase::create($header)`
            // would silently strip them and leave the row in an
            // inconsistent state.
            $purchase = (new Purchase())->forceFill($header);
            $purchase->save();

            $purchase->items()->createMany($normalLines);

            do_action('purchase.after_create', $purchase);

            return $purchase->refresh();
        });
    }
}
