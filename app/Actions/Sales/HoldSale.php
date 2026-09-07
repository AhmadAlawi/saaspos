<?php

namespace App\Actions\Sales;

use App\Models\Company;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Support\NumberFormat;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Park a cart as a held sale — the "Hold" button on the cashier.
 *
 * Held sales are stored as a full Sale row with `status='held'` plus
 * line items, but NO stock movement, NO payments, and NO tax/discount
 * computation. The cashier can resume one any time (load back into the
 * cart) or void it from the held drawer.
 *
 * Numbering: held sales get a `HOLD-{STORE}-YYYYMM-NNNN` number from a
 * separate sequence so we don't burn proper `SALE-` numbers on tickets
 * that might never complete. The held row is destroyed on resume — the
 * new sale that's eventually completed gets a fresh `SALE-` number.
 *
 * @phpstan-type HeldSaleInput array{
 *     store_id:int,
 *     customer_id:?int,
 *     held_label:?string,
 *     notes:?string,
 *     items:array<int,array{
 *         product_id:int,
 *         variant_id:?int,
 *         quantity:numeric-string,
 *         unit_price:numeric-string
 *     }>
 * }
 */
class HoldSale
{
    public function __invoke(array $input, ?User $cashier): Sale
    {
        if (empty($input['items'] ?? [])) {
            throw new RuntimeException(__('sales.errors.empty_cart'));
        }
        if (! $cashier) {
            throw new RuntimeException('Cashier user is required.');
        }

        return DB::transaction(function () use ($input, $cashier) {
            $storeId = (int) $input['store_id'];
            $store   = Store::query()->where('is_active', true)->findOrFail($storeId);

            // Subtotal as the sum of line totals. Tax and discount are
            // not computed here — they get recomputed authoritatively
            // when the held cart resumes and the cashier checks out.
            $subtotal = '0';
            foreach ($input['items'] as $line) {
                $qty   = (string) $line['quantity'];
                $price = (string) $line['unit_price'];
                $subtotal = bcadd($subtotal, bcmul($qty, $price, 8), 4);
            }

            // Discount the cashier had set in the cart at hold-time.
            // Persisted verbatim ({type, value}) so resume restores the
            // same form — a held 10% ticket comes back as 10%, not as
            // its computed currency equivalent (which would drift if the
            // cart was edited before checkout).
            $heldDiscount = null;
            if (isset($input['discount']) && is_array($input['discount'])) {
                $d = $input['discount'];
                $type  = $d['type']  ?? null;
                $value = isset($d['value']) ? (float) $d['value'] : 0;
                if (in_array($type, ['pct', 'amt'], true) && $value > 0) {
                    $heldDiscount = ['type' => $type, 'value' => (string) $value];
                }
            }

            $sale = new Sale();
            $sale->forceFill([
                'store_id'       => $store->id,
                'cashier_id'     => $cashier->id,
                'customer_id'    => $input['customer_id'] ?? null,
                'number'         => $this->nextHoldNumber($store),
                'sale_date'      => now()->toDateString(),
                'sale_datetime'  => now(),
                'status'         => Sale::STATUS_HELD,
                'currency_code'  => app_currency()['code'],
                'subtotal'       => $subtotal,
                'grand_total'    => $subtotal,
                'balance_due'    => $subtotal,
                'held_label'     => $input['held_label'] ?? null,
                'held_by'        => $cashier->id,
                'held_at'        => now(),
                'held_discount'  => $heldDiscount,
                'notes'          => $input['notes'] ?? null,
            ])->save();

            // Pre-load products for snapshot fields — sale_items requires
            // product_name_snapshot / sku_snapshot at insert (the
            // not-null guard prevents writing a "ghost" line).
            $productIds = array_map(fn ($l) => (int) $l['product_id'], $input['items']);
            $products   = Product::query()->with('unit:id,code')->whereIn('id', $productIds)->get()->keyBy('id');

            foreach ($input['items'] as $index => $line) {
                $product   = $products->get((int) $line['product_id']);
                $lineTotal = bcmul((string) $line['quantity'], (string) $line['unit_price'], 4);
                SaleItem::query()->forceCreate([
                    'sale_id'               => $sale->id,
                    'product_id'            => (int) $line['product_id'],
                    'variant_id'            => isset($line['variant_id']) && $line['variant_id'] !== '' ? (int) $line['variant_id'] : null,
                    'product_name_snapshot' => (string) ($product?->name ?? ''),
                    'sku_snapshot'          => $product?->sku,
                    'barcode_snapshot'      => $product?->barcode,
                    'sort_order'            => $index + 1,
                    'quantity'              => (string) $line['quantity'],
                    'unit'                  => (string) ($product?->unit?->code ?? 'pc'),
                    'unit_price'            => (string) $line['unit_price'],
                    'line_subtotal'         => $lineTotal,
                    'line_total'            => $lineTotal,
                    'notes'                 => isset($line['notes']) && $line['notes'] !== '' ? (string) $line['notes'] : null,
                ]);

                // Reserve the held line's qty against stock_levels so
                // another cashier can't oversell what this hold has
                // promised. Skip for products that don't track stock
                // (services, kits with non-stocked components, etc.).
                if ($product?->track_stock) {
                    $this->reserveStock(
                        storeId:   $store->id,
                        productId: (int) $line['product_id'],
                        variantId: isset($line['variant_id']) && $line['variant_id'] !== '' ? (int) $line['variant_id'] : null,
                        delta:     (string) $line['quantity'],
                    );
                }
            }

            return $sale->load('items');
        });
    }

    /**
     * Bump the `reserved_quantity` on the matching stock_level row.
     * Mirrors the find-or-create pattern in RecordStockMovement so a
     * fresh-install product (no level row yet) still gets reserved.
     * Locks the row for the lifetime of the transaction.
     *
     * @internal Used by both this action and `releaseStock()` (sign-
     *           flipped delta) — kept here so the held-sale flow owns
     *           its own reservation accounting and doesn't pollute
     *           {@see RecordStockMovement}, which is for ledgered moves.
     */
    private function reserveStock(int $storeId, int $productId, ?int $variantId, string $delta): void
    {
        $level = StockLevel::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->lockForUpdate()
            ->first();

        if ($level === null) {
            try {
                $level = new StockLevel([
                    'store_id'              => $storeId,
                    'product_id'            => $productId,
                    'variant_id'            => $variantId,
                    'quantity'              => 0,
                    'reserved_quantity'     => 0,
                    'weighted_average_cost' => 0,
                ]);
                $level->save();
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                $level = StockLevel::query()
                    ->where('store_id', $storeId)
                    ->where('product_id', $productId)
                    ->where('variant_id', $variantId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        $level->forceFill([
            'reserved_quantity' => bcadd((string) $level->reserved_quantity, $delta, 4),
        ])->save();
    }

    /**
     * Next number for a held sale — pulls the admin-configured format
     * (default `HOLD-{store}-{Ym}-{seq:04}`) and resolves the next
     * sequence value using {@see NumberFormat}.
     */
    private function nextHoldNumber(Store $store): string
    {
        $when   = now();
        $format = (Company::current() ?? new Company())->numberFormat('hold');
        $prefix = NumberFormat::prefix($format, $store, $when);

        $latest = Sale::withTrashed()
            ->where('store_id', $store->id)
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = $latest ? NumberFormat::extractSeq($latest) + 1 : 1;

        return NumberFormat::render($format, $store, $when, $seq);
    }
}
