<?php

namespace App\Actions\Sales;

use App\Actions\Customers\EarnLoyaltyPoints;
use App\Exceptions\SaleAlreadyClaimed;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleClaimLink;
use Illuminate\Support\Facades\DB;

/**
 * Attaches a customer to an already-completed sale and retroactively
 * awards loyalty points for it — the backing action for the public
 * claim flow ({@see \App\Http\Controllers\SaleClaimController}), used
 * when a walk-in customer wasn't attached at checkout.
 *
 * Safe to reuse {@see EarnLoyaltyPoints} as-is: the original
 * `sale.after_complete` hook already no-op'd for this sale (it early-
 * returns on a null `customer_id`), so calling it here is the FIRST
 * time points are ever computed for this sale — not a double-award.
 * Only the loyalty-earn side effect is replayed here, not the rest of
 * the `sale.after_complete` chain (e.g. accounting entries), which
 * already ran at original completion time and shouldn't run twice.
 */
class AttachCustomerToSale
{
    public function __construct(private EarnLoyaltyPoints $earnPoints) {}

    public function handle(SaleClaimLink $link, Customer $customer): Sale
    {
        return DB::transaction(function () use ($link, $customer) {
            $link = SaleClaimLink::query()->lockForUpdate()->findOrFail($link->id);
            if ($link->isClaimed()) {
                throw new SaleAlreadyClaimed();
            }

            $sale = Sale::query()->lockForUpdate()->findOrFail($link->sale_id);
            if ($sale->customer_id) {
                throw new SaleAlreadyClaimed();
            }

            $sale->forceFill(['customer_id' => $customer->id])->save();

            $link->forceFill([
                'claimed_at'           => now(),
                'claimed_customer_id'  => $customer->id,
            ])->save();

            $this->earnPoints->handle($sale->fresh());

            return $sale->fresh();
        });
    }
}
