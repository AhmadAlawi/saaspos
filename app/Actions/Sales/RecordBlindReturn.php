<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\RecordStockMovement;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Store;
use App\Models\User;
use App\Support\NumberFormat;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Record a "blind" refund — a cashier scans a barcode with no original
 * invoice in hand, so there's no `sales`/`sale_items` row to attach the
 * return to. Sibling of {@see RecordSaleReturn}, trimmed down for the
 * cases that don't apply here:
 *   - no parent Sale to validate/lock/update `quantity_returned` on —
 *     each line snapshots whatever product/variant the barcode resolved
 *     to, plus the price the cashier had on screen (already validated
 *     by the caller against a `refund_approval` token ceiling)
 *   - no gateway charge to reverse — there's no original transaction to
 *     point a Stripe/Razorpay refund at, so gateway tenders are rejected
 *     outright; a blind refund can only pay out cash / a manual method
 *   - still restocks (when requested) via the same
 *     {@see RecordStockMovement}, and still gets a real refund number
 *     from the same `company.refund_number_format` sequence
 *
 * @phpstan-type BlindRefundInput array{
 *     store_id:int,
 *     reason_code_id:int,
 *     refund_method_id:?int,
 *     restock:bool,
 *     notes:?string,
 *     client_uuid:?string,
 *     items:array<int, array{barcode:string, quantity:numeric-string, unit_price:numeric-string}>
 * }
 */
class RecordBlindReturn
{
    public function __construct(
        private readonly RecordStockMovement $recordMovement,
    ) {}

    public function __invoke(array $input, ?User $cashier): SaleReturn
    {
        if (! $cashier) {
            throw new RuntimeException('Cashier user is required.');
        }
        if (empty($input['items'] ?? [])) {
            throw new RuntimeException(__('sales.errors.refund_empty'));
        }

        if (! empty($input['client_uuid'])) {
            $existing = SaleReturn::query()->where('client_uuid', $input['client_uuid'])->first();
            if ($existing) return $existing->load('items');
        }

        $store = Store::query()->where('is_active', true)->findOrFail((int) $input['store_id']);

        $method = $input['refund_method_id'] ? PaymentMethod::query()->find($input['refund_method_id']) : null;
        if ($method && in_array($method->provider, Sale::GATEWAY_PROVIDERS, true)) {
            throw new RuntimeException(__('sales.errors.refund_blind_no_gateway'));
        }

        return DB::transaction(function () use ($input, $store, $method, $cashier) {
            $subtotal = '0';
            $grand    = '0';
            $rows     = [];

            foreach ($input['items'] as $line) {
                $barcode = trim((string) $line['barcode']);
                $qty     = (string) $line['quantity'];
                $price   = (string) $line['unit_price'];

                if (bccomp($qty, '0', 4) <= 0 || bccomp($price, '0', 4) <= 0) {
                    throw new RuntimeException(__('sales.errors.refund_qty_zero', ['name' => $barcode]));
                }

                $variant = ProductVariant::where('barcode', $barcode)->orWhere('sku', $barcode)->first();
                $product = $variant?->product ?? Product::where('barcode', $barcode)->orWhere('sku', $barcode)->first();

                // Extra/alternate barcode (see App\Models\ProductBarcode) —
                // last resort, after the variant/product barcode and SKU.
                if (! $product) {
                    $product = Product::findByAnyBarcode($barcode);
                }

                if (! $product) {
                    throw new RuntimeException(__('sales.errors.refund_blind_unknown_barcode', ['barcode' => $barcode]));
                }

                $lineTotal = bcmul($qty, $price, 4);
                $subtotal  = bcadd($subtotal, $lineTotal, 4);
                $grand     = bcadd($grand, $lineTotal, 4);

                $rows[] = [
                    'product'  => $product,
                    'variant'  => $variant,
                    'barcode'  => $barcode,
                    'qty'      => $qty,
                    'price'    => $price,
                    'total'    => $lineTotal,
                ];
            }

            $return = new SaleReturn();
            $return->forceFill([
                'store_id'               => $store->id,
                'sale_id'                => null,
                'is_blind'               => true,
                'client_uuid'            => $input['client_uuid'] ?? null,
                'original_currency_code' => $store->currency_code ?? null,
                'exchange_rate_to_active' => '1',
                'number'                 => $this->nextRefundNumber($store),
                'return_date'            => now()->toDateString(),
                'cashier_id'             => $cashier->id,
                'reason_code_id'         => (int) $input['reason_code_id'],
                'subtotal'               => $subtotal,
                'tax_total'              => '0',
                'grand_total'            => $grand,
                'refund_method_id'       => $input['refund_method_id'] ?? null,
                'restock'                => (bool) ($input['restock'] ?? true),
                'notes'                  => $input['notes'] ?? null,
                'status'                 => SaleReturn::STATUS_COMPLETED,
                'created_by'             => $cashier->id,
            ])->save();

            // Same bucketing as RecordSaleReturn — gateway is excluded
            // above, so this only ever lands in cash or original-method
            // (a non-gateway manual tender, e.g. "Bank transfer").
            if ($method?->code === 'store_credit') {
                $return->forceFill(['refunded_to_store_credit' => $grand])->save();
            } elseif ($method && $method->type !== 'cash') {
                $return->forceFill(['refunded_to_original_method' => $grand])->save();
            } else {
                $return->forceFill(['refunded_in_cash' => $grand])->save();
            }

            foreach ($rows as $r) {
                /** @var Product $product */
                $product = $r['product'];
                /** @var ProductVariant|null $variant */
                $variant = $r['variant'];

                SaleReturnItem::query()->forceCreate([
                    'sale_return_id'      => $return->id,
                    'sale_item_id'        => null,
                    'product_id'          => $product->id,
                    'variant_id'          => $variant?->id,
                    'sku_snapshot'        => $variant?->sku ?: $product->sku,
                    'barcode_snapshot'    => $r['barcode'],
                    'name_snapshot'       => $product->name,
                    'quantity'            => $r['qty'],
                    'unit_price_snapshot' => $r['price'],
                    'tax_amount'          => '0',
                    'line_total'          => $r['total'],
                    'restock'             => null, // inherits the header flag
                ]);

                $restock = (bool) ($input['restock'] ?? true);
                if ($restock) {
                    ($this->recordMovement)(
                        storeId:       $store->id,
                        productId:     $product->id,
                        variantId:     $variant?->id,
                        batchId:       null,
                        quantityDelta: $r['qty'],
                        type:          'return',
                        referenceType: SaleReturn::class,
                        referenceId:   $return->id,
                        notes:         "Blind refund {$return->number}",
                        createdBy:     $cashier->id,
                    );
                }
            }

            do_action('sale.after_return', $return);

            return $return->load('items');
        });
    }

    /** Same sequence as {@see RecordSaleReturn::nextRefundNumber()} — kept
     *  self-contained here rather than shared, per this codebase's
     *  preference for single-file actions. */
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
