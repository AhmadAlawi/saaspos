<?php

namespace App\Actions\Sales;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;

/**
 * A "corrected" copy of the original sale for the customer's own
 * records after a refund — same sale number, but with fully-refunded
 * lines dropped and partially-refunded lines reduced to what's left.
 * Deliberately reuses the original `Sale::number` rather than minting a
 * new one (confirmed with the user: acceptable since this store has no
 * fiscal e-invoicing that requires unique numbers per printed document).
 *
 * Returns an in-memory clone — never persisted, never re-posted to
 * accounting (the original sale's journal entry stands; this is a
 * printed convenience copy, not a new transaction). Payments are
 * intentionally omitted: the original tender/change no longer
 * reconciles against the reduced total, and reconstructing a correct
 * "refund applied to which payment" breakdown is a separate feature.
 * `additional_charges_total` and `rounding_adjustment` are zeroed —
 * neither is attributable to specific line items, so there's no
 * principled way to prorate them onto what remains.
 */
class BuildPostRefundSaleCopy
{
    public function __invoke(Sale $sale): Sale
    {
        $sale->loadMissing(['items', 'store', 'customer', 'cashier']);

        $refundedQtyByItem = SaleReturnItem::query()
            ->whereIn('sale_item_id', $sale->items->pluck('id'))
            ->whereHas('saleReturn', fn ($q) => $q->where('status', SaleReturn::STATUS_COMPLETED))
            ->selectRaw('sale_item_id, SUM(quantity) as refunded_qty')
            ->groupBy('sale_item_id')
            ->pluck('refunded_qty', 'sale_item_id');

        $remainingItems = collect();
        $subtotal = '0';
        $discount = '0';
        $tax      = '0';
        $grand    = '0';

        foreach ($sale->items as $item) {
            $refundedQty  = (string) ($refundedQtyByItem[$item->id] ?? '0');
            $remainingQty = bcsub((string) $item->quantity, $refundedQty, 4);

            if (bccomp($remainingQty, '0', 4) <= 0) {
                continue; // fully refunded — drop the line entirely
            }

            // Proportional reduction — assumes tax/discount are spread
            // evenly per unit, the standard assumption for a quantity-based
            // partial refund (matches how RecordSaleReturn itself treats
            // a partial-quantity return).
            $fraction = bcdiv($remainingQty, (string) $item->quantity, 8);

            $clone = $item->replicate();
            $clone->id                 = $item->id; // keep for display/lookup only — never saved
            $clone->quantity           = $remainingQty;
            $clone->line_subtotal      = bcmul((string) $item->line_subtotal, $fraction, 4);
            $clone->discount_amount    = bcmul((string) $item->discount_amount, $fraction, 4);
            $clone->tax_amount         = bcmul((string) $item->tax_amount, $fraction, 4);
            $clone->line_total         = bcmul((string) $item->unit_price, $remainingQty, 4);

            $subtotal = bcadd($subtotal, (string) $clone->line_subtotal, 4);
            $discount = bcadd($discount, (string) $clone->discount_amount, 4);
            $tax      = bcadd($tax, (string) $clone->tax_amount, 4);
            $grand    = bcadd($grand, (string) $clone->line_total, 4);

            $remainingItems->push($clone);
        }

        $copy = $sale->replicate();
        $copy->id                        = $sale->id; // same number/id for display — never saved
        $copy->number                    = $sale->number;
        $copy->subtotal                  = $subtotal;
        $copy->discount_total            = $discount;
        $copy->tax_total                 = $tax;
        $copy->additional_charges_total  = '0';
        $copy->rounding_adjustment       = '0';
        $copy->grand_total               = $grand;
        $copy->change_returned           = '0';

        $copy->setRelation('items', $remainingItems->values());
        $copy->setRelation('payments', collect());
        $copy->setRelation('store', $sale->store);
        $copy->setRelation('customer', $sale->customer);
        $copy->setRelation('cashier', $sale->cashier);

        return $copy;
    }
}
