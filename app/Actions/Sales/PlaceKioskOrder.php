<?php

namespace App\Actions\Sales;

use App\Actions\Products\ResolveProductPrice;
use App\Exceptions\InsufficientStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Place a self-ordering kiosk order — the customer-facing counterpart of
 * {@see CompleteSale}. Produces a `status = placed` Sale that lands in the
 * staff orders queue and is paid at the counter (see
 * docs/features/kiosk-self-ordering.md §4).
 *
 * Unlike the cashier, the kiosk NEVER sends prices — the client cart carries
 * only product_id + quantity. Prices are resolved server-side via
 * {@see ResolveProductPrice} and the cart is costed through the shared
 * {@see PriceCart}, so a placed order shows a real, authoritative total.
 *
 * No payment is taken (that happens at the till). Stock IS reserved — exactly
 * like a held ticket — so the pending order can't be oversold; the reservation
 * is released when staff accept the order into the till (placed → held →
 * resume) or reject it. Idempotent by `local_uuid` so an offline replay or a
 * double-tap can't double-post.
 *
 * Input:
 *   $header = ['store_id'=>int, 'terminal_id'=>?int, 'customer_id'=>?int,
 *              'currency_code'=>?string, 'local_uuid'=>?string, 'kiosk_note'=>?string]
 *   $lines  = [['product_id'=>int, 'variant_id'=>?int, 'quantity'=>numeric-string,
 *               'notes'=>?string], …]
 *
 * Hooks:
 *   - filter `kiosk.order.fillable` → ([$header, $lines])
 *   - action `kiosk.before_place`   → ($header, $lines)
 *   - action `kiosk.order_placed`   → ($sale)
 *
 * @param array<string, mixed>             $header
 * @param array<int, array<string, mixed>> $lines
 */
class PlaceKioskOrder
{
    public function __construct(
        private readonly PriceCart $priceCart,
        private readonly ResolveProductPrice $resolvePrice,
        private readonly GenerateKioskPickupCode $pickupCode,
    ) {}

    public function __invoke(array $header, array $lines, ?User $staff = null): Sale
    {
        [$header, $lines] = apply_filters('kiosk.order.fillable', [$header, $lines]);

        if (empty($lines)) {
            throw new RuntimeException(__('sales.errors.empty_cart'));
        }

        $clientUuid = isset($header['local_uuid']) && $header['local_uuid'] !== ''
            ? (string) $header['local_uuid']
            : (string) Str::uuid();

        // Idempotency short-circuit — a retried submission returns the
        // existing order instead of placing a duplicate.
        if ($existing = Sale::query()->where('local_uuid', $clientUuid)->first()) {
            return $existing->load('items');
        }

        do_action('kiosk.before_place', $header, $lines);

        $sale = DB::transaction(function () use ($header, $lines, $clientUuid, $staff) {
            $storeId = (int) $header['store_id'];
            $store   = Store::query()->where('is_active', true)->findOrFail($storeId);

            // ── Resolve products + server-side prices for every line. The
            // client is never trusted for price; we look it up per (product,
            // variant, store).
            $resolvedLines = [];
            foreach (array_values($lines) as $i => $raw) {
                $product = Product::query()->with('unit:id,code')->findOrFail((int) $raw['product_id']);

                $variant = null;
                if (isset($raw['variant_id']) && $raw['variant_id'] !== '') {
                    $variant = ProductVariant::query()->find((int) $raw['variant_id']);
                }

                $qty = (string) ($raw['quantity'] ?? '0');
                if (bccomp($qty, '0', 4) <= 0) {
                    throw new RuntimeException("Line {$i}: quantity must be positive.");
                }

                $prices = ($this->resolvePrice)($product, $storeId, $variant);

                $resolvedLines[] = [
                    'raw' => [
                        'product_id' => (int) $product->id,
                        'variant_id' => $variant?->id,
                        'quantity'   => $qty,
                        'unit_price' => $prices['charge_price'],
                        'notes'      => isset($raw['notes']) && $raw['notes'] !== '' ? (string) $raw['notes'] : null,
                    ],
                    'product' => $product,
                    'variant' => $variant,
                    'index'   => $i,
                ];
            }

            // ── Authoritative pricing via the shared PriceCart — same math
            // the cashier + gateway charge use.
            $priced = $this->priceCart->handle($resolvedLines, $store);

            // ── The placed Sale row. cashier_id is NOT NULL, so we attribute
            // the order to the staff member whose session is running the
            // kiosk (the station's supervisor). No payment, no shift.
            $staffId = $staff?->id ?? auth()->id();

            $sale = new Sale();
            $sale->forceFill([
                'store_id'      => $store->id,
                'terminal_id'   => isset($header['terminal_id']) && $header['terminal_id'] !== '' ? (int) $header['terminal_id'] : current_terminal_id(),
                'cashier_id'    => $staffId,
                'customer_id'   => isset($header['customer_id']) && $header['customer_id'] !== '' ? (int) $header['customer_id'] : null,
                'number'        => $this->nextOrderNumber($store),
                'local_uuid'    => $clientUuid,
                'sale_date'     => now()->toDateString(),
                'sale_datetime' => now(),
                'status'        => Sale::STATUS_PLACED,
                'origin'        => 'kiosk',
                'placed_at'     => now(),
                'pickup_code'   => ($this->pickupCode)((int) $store->id, (string) ($header['pickup_prefix'] ?? 'K')),
                'kiosk_note'    => isset($header['kiosk_note']) && $header['kiosk_note'] !== '' ? (string) $header['kiosk_note'] : null,
                // The shopper scanned the machine's static UPI QR and says they
                // paid. This is a CLAIM, not a payment: no `sale_payments` row
                // is written and `balance_due` stays at the full total, because
                // a static UPI QR has no callback to confirm the transfer.
                // Staff verify it against their own UPI app and settle at the
                // till. See docs/features/kiosk-self-ordering.md §5.
                'kiosk_payment_claim_method_id' => ! empty($header['payment_claim_method_id'])
                    ? (int) $header['payment_claim_method_id']
                    : null,
                'currency_code' => (string) ($header['currency_code'] ?? $store->currency_code),
                'subtotal'      => $priced->subtotal,
                'discount_total'=> $priced->discountTotal,
                'tax_total'     => $priced->taxTotal,
                'grand_total'   => $priced->grandTotal,
                'paid_total'    => '0',
                'balance_due'   => $priced->grandTotal,
                'created_by'    => $staffId,
            ])->save();

            // ── Items (authoritative per-line figures from PriceCart).
            foreach ($priced->lines as $pl) {
                /** @var Product $product */
                $product = $pl['product'];
                $raw     = $pl['raw'];

                SaleItem::query()->forceCreate([
                    'sale_id'               => $sale->id,
                    'product_id'            => (int) $product->id,
                    'variant_id'            => $raw['variant_id'] ?? null,
                    'product_name_snapshot' => (string) $product->name,
                    'sku_snapshot'          => $product->sku,
                    'barcode_snapshot'      => $product->barcode,
                    'hsn_snapshot'          => $product->hsn_code,
                    'quantity'              => (string) $raw['quantity'],
                    'unit'                  => (string) ($product->unit?->code ?? 'pc'),
                    'unit_price'            => (string) $raw['unit_price'],
                    'discount_amount'       => $pl['discount'],
                    'tax_group_id'          => $product->tax_group_id,
                    'tax_breakdown'         => $pl['breakdown']->jsonSerialize(),
                    'tax_amount'            => $pl['tax'],
                    'line_subtotal'         => $pl['net'],
                    'line_total'            => $pl['line_total'],
                    'notes'                 => $raw['notes'] ?? null,
                    'sort_order'            => $pl['index'] + 1,
                ]);

                // Reserve the ordered qty so a pending kiosk order can't be
                // oversold at the till. Mirrors HoldSale::reserveStock() —
                // released when staff accept (placed→held→resume) or reject.
                if ($product->track_stock) {
                    $this->reserveStock(
                        storeId:   $store->id,
                        product:   $product,
                        variantId: $raw['variant_id'] ?? null,
                        delta:     (string) $raw['quantity'],
                    );
                }
            }

            return $sale->load('items');
        });

        // ── Post-commit bookkeeping. The order is ALREADY saved, so a hook
        // or notification blowing up here must never tell the customer their
        // order failed (it would also make them tap again and place a second
        // one). Log it and move on.
        try {
            do_action('kiosk.order_placed', $sale);

            // Ping staff so a kiosk order isn't missed. Gated to sales viewers.
            notify_admins(
                key:        'kiosk.order_placed',
                title:      __('kiosk-orders.notify.title'),
                message:    __('kiosk-orders.notify.message', ['code' => $sale->pickup_code, 'total' => format_money($sale->grand_total)]),
                icon:       'pos',
                url:        route('admin.kiosk-orders.index'),
                permission: 'sales.view_all',
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Kiosk order placed but post-commit notify failed.', [
                'sale_id' => $sale->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return $sale;
    }

    /**
     * A throwaway `ORDER-{store}-{Ymd}-{seq}` number for the placed row. The
     * eventual completed sale gets a fresh `SALE-` number when staff ring it
     * up, so we don't burn a proper sale number on an order that may never
     * be paid — same reasoning as held tickets.
     */
    private function nextOrderNumber(Store $store): string
    {
        $prefix = 'ORDER-'.$store->code.'-'.now()->format('Ymd').'-';

        $latest = Sale::withTrashed()
            ->where('store_id', $store->id)
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Assert the shelf can cover this line, then bump `reserved_quantity` on
     * the matching stock level (find-or-creating the row). Replicates
     * {@see HoldSale::reserveStock()} so the kiosk owns its reservation
     * accounting without refactoring the hold flow; released via
     * {@see ReleaseHeldReservation} once the order becomes a held ticket.
     *
     * The availability check runs under the SAME row lock as the bump, so two
     * customers tapping the last unit at once serialise here and the second
     * gets {@see InsufficientStock} instead of overselling. The kiosk hides
     * out-of-stock tiles client-side, but its catalog cache can be stale — so
     * this is the authoritative gate.
     */
    private function reserveStock(int $storeId, Product $product, ?int $variantId, string $delta): void
    {
        $productId = (int) $product->id;

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

        // Available = on-hand minus what other holds / kiosk orders already
        // promised. Floor at 0 so a drifted reservation can't read negative.
        $available = bcsub((string) $level->quantity, (string) $level->reserved_quantity, 4);
        if (bccomp($available, '0', 4) < 0) {
            $available = '0.0000';
        }

        if (bccomp($delta, $available, 4) > 0) {
            throw new InsufficientStock(
                productName: (string) $product->name,
                available:   $available,
                requested:   $delta,
            );
        }

        $level->forceFill([
            'reserved_quantity' => bcadd((string) $level->reserved_quantity, $delta, 4),
        ])->save();
    }
}
