<?php

namespace App\Actions\Purchases;

use App\Actions\Inventory\RecordStockMovement;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Create and immediately post a purchase return.
 *
 * Mirrors {@see ReceivePurchase} in reverse:
 *   1. Lock purchase + supplier rows.
 *   2. For each submitted line with quantity > 0:
 *      - Create a PurchaseReturnItem.
 *      - If restock=true, call RecordStockMovement with a negative delta
 *        so the goods go back into supplier limbo (stock decreases).
 *   3. Compute totals (subtotal + proportional tax per line).
 *   4. Persist the return header with status='posted'.
 *   5. Decrement supplier.outstanding_balance by grand_total.
 *   6. Fire purchase.after_return hook.
 *
 * No draft→post step in v1.0. Returns are posted on creation.
 * Quantities exceeding the originally received quantity are silently
 * capped to avoid negative-stock edge cases.
 */
class CreatePurchaseReturn
{
    public function __construct(
        private RecordStockMovement $recordMovement,
        private GeneratePurchaseReturnNumber $generateNumber,
    ) {}

    private static array $RETURNABLE = [
        Purchase::STATUS_RECEIVED,
        Purchase::STATUS_PARTIALLY_PAID,
        Purchase::STATUS_PAID,
    ];

    public function __invoke(Purchase $purchase, array $data, ?User $creator = null): PurchaseReturn
    {
        if (! in_array($purchase->status, self::$RETURNABLE, true)) {
            throw new \RuntimeException(
                __('purchases.returns.errors.not_returnable', [
                    'number' => $purchase->number,
                    'status' => $purchase->status,
                ])
            );
        }

        do_action('purchase.before_return', $purchase);

        return DB::transaction(function () use ($purchase, $data, $creator) {
            // Re-lock inside the transaction — catches races where two
            // operators try to return the same purchase simultaneously.
            $locked = Purchase::query()
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! in_array($locked->status, self::$RETURNABLE, true)) {
                throw new \RuntimeException(__('purchases.returns.errors.not_returnable', [
                    'number' => $purchase->number,
                    'status' => $locked?->status ?? 'unknown',
                ]));
            }
            $purchase = $locked;

            $supplier = Supplier::query()
                ->whereKey($purchase->supplier_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Locked for the transaction — same fix as CompleteSale.php's
            // sale-number race: serializes concurrent returns against the
            // same store so GeneratePurchaseReturnNumber's unlocked
            // MAX(number) read can't collide.
            Store::query()->where('id', $purchase->store_id)->lockForUpdate()->firstOrFail();

            $number = ($this->generateNumber)($purchase->store_id);

            $return = PurchaseReturn::create([
                'store_id'    => $purchase->store_id,
                'supplier_id' => $purchase->supplier_id,
                'purchase_id' => $purchase->id,
                'number'      => $number,
                'return_date' => $data['return_date'],
                'notes'       => $data['notes'] ?? null,
                'created_by'  => $creator?->id,
                'updated_by'  => $creator?->id,
            ]);

            $purchase->load(['items.product:id,name,sku,track_batches']);
            $originalItems = $purchase->items->keyBy('id');

            $subtotal  = '0.0000';
            $taxAmount = '0.0000';

            foreach ($data['items'] as $line) {
                $qty = rtrim(rtrim(number_format((float) ($line['quantity'] ?? 0), 4, '.', ''), '0'), '.');
                if ($qty === '' || $qty === '0') {
                    $qty = '0.0000';
                }
                if (bccomp($qty, '0', 4) <= 0) {
                    continue;
                }

                $origItem = $originalItems[(int) $line['purchase_item_id']] ?? null;
                if (! $origItem) {
                    continue;
                }

                // Cap at the received quantity so we can't return more than arrived.
                $maxQty = (string) ($origItem->received_quantity ?? $origItem->quantity);
                if (bccomp($qty, $maxQty, 4) > 0) {
                    $qty = $maxQty;
                }

                $unitCost     = (string) $origItem->unit_cost;
                $origQty      = (string) $origItem->quantity;
                $lineSubtotal = bcmul($qty, $unitCost, 4);

                // Tax: proportional share of the original line's tax.
                $lineTax = '0.0000';
                if (bccomp($origQty, '0', 4) > 0 && bccomp((string) $origItem->tax_amount, '0', 4) > 0) {
                    $lineTax = bcmul(
                        bcdiv($qty, $origQty, 8),
                        (string) $origItem->tax_amount,
                        4,
                    );
                }

                $lineTotal = bcadd($lineSubtotal, $lineTax, 4);
                $restock   = (bool) ($line['restock'] ?? true);

                PurchaseReturnItem::create([
                    'purchase_return_id' => $return->id,
                    'purchase_item_id'   => $origItem->id,
                    'quantity'           => $qty,
                    'unit_cost_snapshot' => $unitCost,
                    'tax_amount'         => $lineTax,
                    'line_total'         => $lineTotal,
                    'restock'            => $restock,
                    'notes'              => $line['notes'] ?? null,
                ]);

                $subtotal  = bcadd($subtotal,  $lineSubtotal, 4);
                $taxAmount = bcadd($taxAmount, $lineTax,      4);

                // Remove returned goods from stock when restock=true.
                // Stock goes negative here (they're being returned to supplier),
                // so the delta is negative.
                if ($restock && $origItem->product) {
                    ($this->recordMovement)(
                        storeId:       (int) $purchase->store_id,
                        productId:     (int) $origItem->product_id,
                        variantId:     $origItem->variant_id,
                        batchId:       $origItem->batch_id,
                        quantityDelta: '-'.$qty,
                        type:          'purchase_return',
                        referenceType: PurchaseReturn::class,
                        referenceId:   $return->id,
                        notes:         "Return {$number}",
                        createdBy:     $creator?->id,
                    );
                }
            }

            $grandTotal = bcadd($subtotal, $taxAmount, 4);

            // Cap the cash refund at what's actually been paid. You can return
            // any quantity of goods (that reduces what we owe), but the money
            // back can never exceed what was paid to the supplier — minus
            // whatever earlier returns on this purchase have already refunded.
            $alreadyRefunded = (string) (PurchaseReturn::query()
                ->where('purchase_id', $purchase->id)
                ->whereKeyNot($return->id)
                ->sum('refund_amount') ?: '0');
            $refundable   = bcsub((string) $purchase->paid_total, $alreadyRefunded, 4);
            if (bccomp($refundable, '0', 4) < 0) {
                $refundable = '0.0000';
            }
            $refundAmount = bccomp($grandTotal, $refundable, 4) > 0 ? $refundable : $grandTotal;

            $return->forceFill([
                'subtotal'      => $subtotal,
                'tax_amount'    => $taxAmount,
                'grand_total'   => $grandTotal,
                'refund_amount' => $refundAmount,
                'status'        => PurchaseReturn::STATUS_POSTED,
            ])->save();

            // Reduce what we owe the supplier by the returned goods value.
            $supplier->forceFill([
                'outstanding_balance' => bcsub(
                    (string) $supplier->outstanding_balance,
                    $grandTotal,
                    4,
                ),
                'updated_by' => $creator?->id,
            ])->save();

            do_action('purchase.after_return', $return);

            return $return->refresh();
        });
    }
}
