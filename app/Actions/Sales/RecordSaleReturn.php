<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\RecordStockMovement;
use App\Models\Company;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Store;
use App\Models\User;
use App\Support\NumberFormat;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Process a refund / return against an existing completed sale.
 *
 * The action:
 *   - validates each refund line against the parent sale_item's
 *     remaining returnable quantity (`quantity - quantity_returned`)
 *   - allocates the line's tax proportionally against the refund qty
 *   - writes a `sale_returns` header + `sale_return_items` lines in a
 *     single transaction
 *   - bumps the parent sale's `quantity_returned` per line and flips
 *     status to `partially_refunded` / `refunded` based on what's left
 *   - reverses stock movements via {@see RecordStockMovement}
 *     (positive delta, type='return') for any line where restock is
 *     true (per-line override falls back to the header flag)
 *   - is idempotent by `client_uuid` — re-submitting the same UUID
 *     returns the previously-created refund instead of double-refunding
 *
 * The refund_method_id determines which bucket the money lands in:
 *   `payment_methods.type === 'cash'`   → `refunded_in_cash`
 *   `payment_methods.code  === 'store_credit'` → `refunded_to_store_credit`
 *   anything else                       → `refunded_to_original_method`
 *
 * Numbering: pulls the admin-configured format from
 * `company.refund_number_format` (default `REFUND-{store}-{Ym}-{seq:04}`).
 *
 * @phpstan-type RefundInput array{
 *     sale_id:int,
 *     reason_code_id:int,
 *     refund_method_id:?int,
 *     restock:bool,
 *     notes:?string,
 *     client_uuid:?string,
 *     items:array<int, array{
 *         sale_item_id:int,
 *         quantity:numeric-string,
 *         restock:?bool,
 *         notes:?string
 *     }>
 * }
 */
class RecordSaleReturn
{
    public function __construct(
        private readonly RecordStockMovement $recordMovement,
        private readonly ReverseGatewayCharge $reverseGateway,
    ) {}

    public function __invoke(array $input, ?User $cashier): SaleReturn
    {
        if (! $cashier) {
            throw new RuntimeException('Cashier user is required.');
        }
        if (empty($input['items'] ?? [])) {
            throw new RuntimeException(__('sales.errors.refund_empty'));
        }

        // Idempotency — re-submitting the same UUID returns the prior
        // refund instead of double-refunding. Mirrors CompleteSale's
        // local_uuid short-circuit.
        if (! empty($input['client_uuid'])) {
            $existing = SaleReturn::query()->where('client_uuid', $input['client_uuid'])->first();
            if ($existing) return $existing->load('items');
        }

        $sale = Sale::query()
            ->where('id', (int) $input['sale_id'])
            ->where('status', '!=', Sale::STATUS_VOIDED)
            ->firstOrFail();

        if ($sale->status === Sale::STATUS_REFUNDED) {
            throw new RuntimeException(__('sales.errors.already_refunded'));
        }

        $saleReturn = DB::transaction(function () use ($input, $sale, $cashier) {
            $store = Store::query()->where('is_active', true)->findOrFail($sale->store_id);

            // Pre-load the lines we're refunding, locked, so two
            // simultaneous refunds can't both pass the
            // remaining-quantity check.
            $itemIds = array_map(fn ($l) => (int) $l['sale_item_id'], $input['items']);
            $items = SaleItem::query()
                ->whereIn('id', $itemIds)
                ->where('sale_id', $sale->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = '0';
            $taxTotal = '0';
            $grand    = '0';
            $rows     = [];

            foreach ($input['items'] as $line) {
                $itemId = (int) $line['sale_item_id'];
                $item   = $items->get($itemId);
                if (! $item) {
                    throw new RuntimeException("Line {$itemId} does not belong to this sale.");
                }

                $qty       = (string) $line['quantity'];
                $remaining = bcsub((string) $item->quantity, (string) $item->quantity_returned, 4);
                if (bccomp($qty, '0', 4) <= 0) {
                    throw new RuntimeException(__('sales.errors.refund_qty_zero', ['name' => $item->product_name_snapshot]));
                }
                if (bccomp($qty, $remaining, 4) > 0) {
                    throw new RuntimeException(__('sales.errors.refund_qty_exceeds', [
                        'name'      => $item->product_name_snapshot,
                        'remaining' => rtrim(rtrim($remaining, '0'), '.'),
                        'requested' => rtrim(rtrim($qty, '0'), '.'),
                    ]));
                }

                // Proportional tax allocation — split the original line's
                // tax_amount by qty so partial refunds give back the
                // right tax slice.
                $ratio = bcdiv($qty, (string) $item->quantity, 8);
                $lineTax = bcmul((string) $item->tax_amount, $ratio, 4);
                $lineSubtotal = bcmul($qty, (string) $item->unit_price, 4);
                // Honour the line's own discount distribution: subtotal
                // proportionally net of the original line discount.
                if ((float) ($item->discount_amount ?? 0) > 0) {
                    $lineSubtotal = bcsub($lineSubtotal, bcmul((string) $item->discount_amount, $ratio, 4), 4);
                }
                $lineTotal = bcadd($lineSubtotal, $lineTax, 4);

                $subtotal = bcadd($subtotal, $lineSubtotal, 4);
                $taxTotal = bcadd($taxTotal, $lineTax, 4);
                $grand    = bcadd($grand, $lineTotal, 4);

                $rows[] = [
                    'item'         => $item,
                    'qty'          => $qty,
                    'unit_price'   => (string) $item->unit_price,
                    'tax_amount'   => $lineTax,
                    'line_total'   => $lineTotal,
                    // null = "inherit the header flag"; true/false = explicit override.
                    // (bool) null would silently kill restocking on lines the
                    // caller didn't override — preserve null when ambiguous.
                    'restock'      => array_key_exists('restock', $line) && $line['restock'] !== null
                        ? (bool) $line['restock']
                        : null,
                    'notes'        => $line['notes'] ?? null,
                ];
            }

            // Header row.
            $return = new SaleReturn();
            $return->forceFill([
                'store_id'                    => $store->id,
                'sale_id'                     => $sale->id,
                'client_uuid'                 => $input['client_uuid'] ?? null,
                'original_currency_code'      => $sale->currency_code,
                'exchange_rate_to_active'     => '1',
                'number'                      => $this->nextRefundNumber($store),
                'return_date'                 => now()->toDateString(),
                'cashier_id'                  => $cashier->id,
                'reason_code_id'              => (int) $input['reason_code_id'],
                'subtotal'                    => $subtotal,
                'tax_total'                   => $taxTotal,
                'grand_total'                 => $grand,
                'refund_method_id'            => $input['refund_method_id'] ?? null,
                'restock'                     => (bool) ($input['restock'] ?? true),
                'notes'                       => $input['notes'] ?? null,
                'status'                      => SaleReturn::STATUS_COMPLETED,
                'created_by'                  => $cashier->id,
            ])->save();

            // Bucketing: cash / store-credit / original-method. The
            // refund_method drives which column carries the amount so
            // the cash drawer reconciliation can pick out cash refunds
            // and the customer-credit ledger can pick out credit ones.
            if ($return->refund_method_id) {
                $method = \App\Models\PaymentMethod::query()->find($return->refund_method_id);
                if ($method?->type === 'cash') {
                    $return->forceFill(['refunded_in_cash' => $grand])->save();
                } elseif ($method?->code === 'store_credit') {
                    $return->forceFill(['refunded_to_store_credit' => $grand])->save();
                } else {
                    $return->forceFill(['refunded_to_original_method' => $grand])->save();
                }
            } else {
                $return->forceFill(['refunded_in_cash' => $grand])->save();
            }

            // Line rows + parent-line update + restock movement.
            foreach ($rows as $r) {
                /** @var SaleItem $item */
                $item = $r['item'];

                SaleReturnItem::query()->forceCreate([
                    'sale_return_id'      => $return->id,
                    'sale_item_id'        => $item->id,
                    'quantity'            => $r['qty'],
                    'unit_price_snapshot' => $r['unit_price'],
                    'tax_amount'          => $r['tax_amount'],
                    'line_total'          => $r['line_total'],
                    'restock'             => $r['restock'],
                    'notes'               => $r['notes'],
                ]);

                $item->forceFill([
                    'quantity_returned' => bcadd((string) $item->quantity_returned, $r['qty'], 4),
                ])->save();

                // Restock honours per-line override → header flag fallback.
                $restock = $r['restock'] ?? (bool) ($input['restock'] ?? true);
                if ($restock && $item->product_id) {
                    ($this->recordMovement)(
                        storeId:       $store->id,
                        productId:     (int) $item->product_id,
                        variantId:     $item->variant_id ? (int) $item->variant_id : null,
                        batchId:       $item->batch_id ? (int) $item->batch_id : null,
                        quantityDelta: $r['qty'],
                        type:          'return',
                        referenceType: SaleReturn::class,
                        referenceId:   $return->id,
                        notes:         "Refund {$return->number}",
                        createdBy:     $cashier->id,
                    );
                }
            }

            // Update parent sale status based on remaining returnable
            // qty across all lines. Fully refunded when no line has
            // any remaining qty; partial otherwise.
            $sale->load('items');
            $anyRemaining = $sale->items->contains(
                fn (SaleItem $i) => bccomp((string) $i->quantity, (string) $i->quantity_returned, 4) > 0
            );
            $sale->forceFill([
                'status' => $anyRemaining ? Sale::STATUS_PARTIALLY_REFUNDED : Sale::STATUS_REFUNDED,
            ])->save();

            return $return->load('items');
        });

        // Reverse the charge through the original gateway (Stripe, Razorpay,
        // …) for original-method refunds. Runs after commit — never inside
        // the transaction — and never throws: a gateway failure is recorded
        // on the return for retry, the refund itself stands.
        ($this->reverseGateway)($saleReturn);

        // Fired after commit so listeners (e.g. the admin bell) see a
        // persisted return. A failing listener can't roll back the refund.
        do_action('sale.after_return', $saleReturn);

        return $saleReturn;
    }

    /**
     * Next refund number — pulls the admin-configured format from
     * `company.refund_number_format` (default `REFUND-{store}-{Ym}-{seq:04}`).
     */
    private function nextRefundNumber(Store $store): string
    {
        $when   = now();
        $format = (Company::current() ?? new Company())->numberFormat('refund');
        $prefix = NumberFormat::prefix($format, $store, $when);

        $latest = SaleReturn::withTrashed()
            ->where('store_id', $store->id)
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = $latest ? NumberFormat::extractSeq($latest) + 1 : 1;

        return NumberFormat::render($format, $store, $when, $seq);
    }
}
