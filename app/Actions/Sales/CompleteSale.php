<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\RecordStockMovement;
use App\Exceptions\CreditLimitExceeded;
use App\Exceptions\CreditRequiresCustomer;
use App\Exceptions\DiscountAboveThreshold;
use App\Exceptions\DiscountNotAllowed;
use App\Exceptions\ExpiredBatchSale;
use App\Exceptions\InsufficientStock;
use App\Exceptions\ShiftRequired;
use App\Models\Company;
use App\Models\ProductBatch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Actions\Products\ResolveProductPrice;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleDiscount;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Sales\DiscountApprovalToken;
use App\Services\Tax\TaxResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Complete a sale — the single canonical path the cashier flow goes
 * through to finalize a ring-up.
 *
 * Input shape (decimal-strings at 4dp, matching every other money path
 * in the system):
 *
 *   $header = [
 *     'store_id'       => int,
 *     'customer_id'    => ?int,           // null = walk-in
 *     'currency_code'  => 'USD',
 *     'sale_date'      => 'YYYY-MM-DD',
 *     'local_uuid'     => string-uuid|null,  // client-generated idempotency key
 *     'notes'          => ?string,
 *   ]
 *   $lines = [
 *     [
 *       'product_id'        => int,
 *       'variant_id'        => ?int,
 *       'batch_id'          => ?int,        // wired in Slice 3 — required when the product is batch-tracked + has live batches
 *       'quantity'          => decimal-string,
 *       'unit_price'        => decimal-string,  // pre-discount — ignored;
 *                                                //   overwritten server-side
 *                                                //   via ResolveProductPrice
 *       'discount_percent'  => decimal-string,  // optional
 *       'discount_amount'   => decimal-string,  // optional, line-level absolute discount
 *       'notes'             => ?string,
 *     ], …
 *   ]
 *   $payments = [
 *     [
 *       'payment_method_id'  => int,
 *       'amount'             => decimal-string,
 *       'tendered_amount'    => decimal-string|null,    // for cash, the customer's tendered cash
 *       'reference'          => ?string,                // for manual-reference, the txn id
 *       'gateway_provider'   => ?string,                // Slice 2 fields, pass-through
 *       'gateway_payment_id' => ?string,
 *       'gateway_signature'  => ?string,
 *       'gateway_status'     => ?string,
 *     ], …
 *   ]
 *
 * Atomic transaction:
 *   1. Lock per-(store, product, variant) `product_stock_levels` row.
 *   2. Slice-1 negative-stock policy: block if `quantity_on_hand < line_qty`.
 *   3. Resolve tax per line via {@see TaxResolver}, snapshot the breakdown.
 *   4. Compute header totals (subtotal/discount/tax/grand) from rounded line values.
 *   5. Sum of payments MUST equal grand_total exactly (no partial-pay in Slice 1).
 *   6. Generate next `SALE-{STORE}-YYYYMM-NNNN`, insert sales/items/payments.
 *   7. Loop {@see RecordStockMovement} (`type='sale'`, negative delta) per line —
 *      the WAC stays untouched on negative movements.
 *   8. Idempotency: if a sale with this `local_uuid` already exists, return it
 *      unchanged. Lets the cashier retry on network glitches without dupes.
 *
 * Hooks:
 *   - filter `sale.fillable`         → ($header, $lines, $payments)
 *   - action `sale.before_complete`  → ($header, $lines, $payments)
 *   - action `sale.after_complete`   → ($sale)
 *
 * Returns the persisted Sale row (with `items` and `payments` loaded).
 *
 * @param array<string, mixed>            $header
 * @param array<int, array<string, mixed>> $lines
 * @param array<int, array<string, mixed>> $payments
 */
class CompleteSale
{
    public function __construct(
        private readonly TaxResolver $taxResolver,
        private readonly GenerateSaleNumber $generateNumber,
        private readonly RecordStockMovement $recordMovement,
        private readonly PriceCart $priceCart,
        private readonly ResolveProductPrice $resolvePrice,
    ) {}

    public function __invoke(array $header, array $lines, array $payments, ?User $user = null): Sale
    {
        [$header, $lines, $payments] = apply_filters(
            'sale.fillable',
            [$header, $lines, $payments]
        );

        if (empty($lines)) {
            throw new RuntimeException('Cannot complete a sale with no items.');
        }
        // Empty payments is allowed only when a customer is attached
        // (pure-credit sale — entire grand lands on balance_due). The
        // sum-vs-grand check below enforces the rest.
        $hasCustomer = isset($header['customer_id']) && $header['customer_id'] !== '';
        if (empty($payments) && ! $hasCustomer) {
            throw new RuntimeException('Cannot complete a sale with no payments.');
        }

        // Idempotency short-circuit BEFORE the transaction. The
        // `sales.local_uuid` column is uniquely indexed; a retry from
        // the cashier (same uuid) returns the existing row instead of
        // double-posting.
        $clientUuid = isset($header['local_uuid']) && $header['local_uuid'] !== ''
            ? (string) $header['local_uuid']
            : (string) Str::uuid();

        if ($existing = Sale::query()->where('local_uuid', $clientUuid)->first()) {
            return $existing->load(['items', 'payments']);
        }

        do_action('sale.before_complete', $header, $lines, $payments);

        return DB::transaction(function () use ($header, $lines, $payments, $clientUuid, $user) {
            $storeId = (int) $header['store_id'];

            // Locked for the whole transaction — serializes concurrent
            // completions against the same store so GenerateSaleNumber's
            // unlocked MAX(number) read below can't race across two
            // terminals ringing up at the same instant (see docblock on
            // GenerateSaleNumber). Same pattern as the StockLevel/Customer
            // locks further down.
            $store = Store::query()->where('id', $storeId)->lockForUpdate()->firstOrFail();

            $company = Company::current() ?? new Company();

            // Overselling (letting stock go negative) is allowed only when the
            // store opts in AND this user carries `sales.oversell`. Resolved
            // once — the per-line guard below reads it. Non-tracked products
            // are never stock-blocked regardless (services / made-to-order).
            $canOversell = (bool) ($company->cashier()['allow_negative_stock'] ?? false)
                && $user?->hasPermission('sales.oversell');

            // ── 1. Resolve products + locked stock levels for every line.
            $resolvedLines = [];
            foreach (array_values($lines) as $i => $raw) {
                $product = Product::query()->findOrFail((int) $raw['product_id']);

                // Lock the stock level row before checking. Two concurrent
                // cashiers ringing up the last unit must serialize here.
                $level = StockLevel::query()
                    ->where('store_id', $storeId)
                    ->where('product_id', $product->id)
                    ->where('variant_id', $raw['variant_id'] ?? null)
                    ->lockForUpdate()
                    ->first();

                $onHand = $level ? (string) $level->quantity : '0';
                $qty    = (string) ($raw['quantity'] ?? '0');

                if (bccomp($qty, '0', 4) <= 0) {
                    throw new RuntimeException("Line {$i}: quantity must be positive.");
                }

                // Block negative stock — unless the product isn't inventory
                // tracked, or the store allows overselling and this user may
                // do it (see $canOversell above). Batch-tracked lines are
                // never oversold here: the FEFO picker needs a real batch, so
                // the block still applies to them (revisit for backorders).
                $isBatchLine = isset($raw['batch_id']) && $raw['batch_id'] !== '';
                $mayGoNegative = ! $product->track_stock || ($canOversell && ! $isBatchLine);
                if (! $mayGoNegative && bccomp($onHand, $qty, 4) < 0) {
                    throw new InsufficientStock(
                        productName: (string) $product->name,
                        available:   $onHand,
                        requested:   $qty,
                    );
                }

                // Slice 3b: block expired-batch sales when the company
                // setting is on AND the cashier lacks the override
                // permission. Fetched as a separate query so we hit the
                // batches row only when the line cares (it's cheap, but
                // batchless lines should pay nothing).
                $batchId = isset($raw['batch_id']) && $raw['batch_id'] !== ''
                    ? (int) $raw['batch_id']
                    : null;
                if ($batchId !== null) {
                    if ((bool) ($company->block_expired_batch_sale ?? false)) {
                        $hasOverride = $user
                            ? $user->hasPermission('inventory.sell_expired')
                            : false;
                        if (! $hasOverride) {
                            $batch = ProductBatch::query()->find($batchId);
                            if ($batch && $batch->isExpired()) {
                                throw new ExpiredBatchSale(
                                    productName: (string) $product->name,
                                    batchNumber: (string) $batch->batch_number,
                                    expiryDate:  $batch->expiry_date->toDateString(),
                                );
                            }
                        }
                    }
                }

                // The client is never trusted for price — same rule
                // {@see \App\Actions\Sales\PlaceKioskOrder} already
                // follows for kiosk. Whatever unit_price the cashier UI
                // sent gets overwritten with the server-resolved charge
                // price (selling price, static sale_price override, or a
                // running scheduled {@see \App\Models\PriceRule} —
                // strongest wins), so a stale screen, a tampered request,
                // or a promotion that just ended can't ring up the wrong
                // amount. Only the discount fields stay client-supplied,
                // and those are separately governed below.
                $variant = isset($raw['variant_id']) && $raw['variant_id'] !== ''
                    ? ProductVariant::query()->find((int) $raw['variant_id'])
                    : null;
                $raw['unit_price'] = ($this->resolvePrice)($product, $storeId, $variant)['charge_price'];

                $resolvedLines[] = [
                    'raw'     => $raw,
                    'product' => $product,
                    'index'   => $i,
                ];
            }

            // ── 2. Price the cart via the shared PriceCart action — the
            // SAME code the QR/gateway charge uses, so the amount charged
            // can never disagree with the amount recorded here. Holds the
            // invariant sum(items.line_total) == subtotal + tax_total ==
            // grand_total exactly (scale-8-then-round-4-dp throughout).
            $priced = $this->priceCart->handle($resolvedLines, $store);

            $subtotal      = $priced->subtotal;
            $discountTotal = $priced->discountTotal;
            $taxTotal      = $priced->taxTotal;
            $grandTotal    = $priced->grandTotal;
            $itemsToInsert = [];

            // ── Discount governance (Checkout discounts, Slices 1 + 2). ──
            // Any discount needs `sales.discount`; a discount above the
            // store's threshold needs `sales.discount_above_threshold` OR a
            // valid manager-approval token (Slice 2). Enforced server-side
            // so the client checks can't be bypassed.
            $discountApprovedBy = null;
            if (bccomp($discountTotal, '0', 4) > 0) {
                // Effective discount % against the pre-discount gross.
                $gross = '0';
                foreach ($priced->lines as $pl) {
                    $gross = bcadd(
                        $gross,
                        bcmul((string) ($pl['raw']['quantity'] ?? '0'), (string) ($pl['raw']['unit_price'] ?? '0'), 8),
                        8,
                    );
                }
                $effectivePct = bccomp($gross, '0', 4) > 0
                    ? bcdiv(bcmul($discountTotal, '100', 8), $gross, 4)
                    : '0';

                // A customer's admin-configured default discount is pre-
                // authorized: a discount within it needs neither `sales.discount`
                // nor manager approval (the cashier just picked the customer).
                // Read from the actual customer row — NOT a client-sent value —
                // so it can't be spoofed to widen the exemption. The 0.05% hair
                // absorbs 4-dp rounding on the effective-percent computation.
                $customerId = isset($header['customer_id']) && $header['customer_id'] !== ''
                    ? (int) $header['customer_id'] : null;
                $customerDefaultPct = $customerId
                    ? (string) (Customer::whereKey($customerId)->value('default_discount_percent') ?? '0')
                    : '0';
                $exemptCeiling = bccomp($customerDefaultPct, '0', 4) > 0
                    ? bcadd($customerDefaultPct, '0.05', 4)
                    : '0';

                // Only the portion beyond the pre-authorized default is
                // cashier-initiated → govern it. Every such discount now
                // needs a PIN-verified approval token, regardless of
                // whether the acting cashier holds `sales.discount` —
                // the PIN identifies WHO applied it, not just whether the
                // logged-in session is allowed to. Under the store's
                // threshold, any active user's PIN satisfies it; above
                // it, the resolved PIN owner must still hold
                // `sales.discount_above_threshold` (re-checked live here,
                // not trusted from the token, in case it was revoked
                // between approval and ring-up).
                if (bccomp($effectivePct, $exemptCeiling, 4) > 0) {
                    $threshold = (string) ($store->discount_threshold_percent ?? '100');

                    $approverId = app(DiscountApprovalToken::class)
                        ->verify($header['discount_approval'] ?? null, $storeId, $effectivePct);
                    $approver = $approverId ? User::find($approverId) : null;

                    if (! $approver) {
                        throw new DiscountNotAllowed();
                    }
                    if (bccomp($effectivePct, $threshold, 4) > 0
                        && ! $approver->hasPermission('sales.discount_above_threshold', $storeId)) {
                        throw new DiscountAboveThreshold($effectivePct, $threshold);
                    }

                    $discountApprovedBy = $approverId;
                }
            }

            foreach ($priced->lines as $pl) {
                /** @var Product $product */
                $product = $pl['product'];
                $raw     = $pl['raw'];
                $i       = $pl['index'];

                $itemsToInsert[] = [
                    'product_id'            => (int) $product->id,
                    'variant_id'            => isset($raw['variant_id']) && $raw['variant_id'] !== '' ? (int) $raw['variant_id'] : null,
                    'batch_id'              => isset($raw['batch_id']) && $raw['batch_id'] !== ''     ? (int) $raw['batch_id']   : null,
                    'product_name_snapshot' => (string) $product->name,
                    'sku_snapshot'          => $product->sku,
                    'barcode_snapshot'      => $product->barcode,
                    'hsn_snapshot'          => $product->hsn_code,
                    'quantity'              => $this->round4((string) ($raw['quantity'] ?? '0')),
                    'unit'                  => (string) ($product->unit?->code ?? 'pc'),
                    'unit_price'            => $this->round4((string) ($raw['unit_price'] ?? '0')),
                    'unit_cost_snapshot'    => '0',  // Slice 1 — WAC snapshot lands when we have a cost source per line.
                    'discount_percent'      => $this->round4((string) ($raw['discount_percent'] ?? '0')),
                    'discount_amount'       => $this->round4($pl['discount']),
                    'tax_group_id'          => $product->tax_group_id,
                    'tax_breakdown'         => $pl['breakdown']->jsonSerialize(),
                    'tax_amount'            => $pl['tax'],
                    'line_subtotal'         => $pl['net'],
                    'line_total'            => $pl['line_total'],
                    'notes'                 => $raw['notes'] ?? null,
                    'sort_order'            => $i,
                ];
            }

            // ── 3. Payments invariant.
            //
            // Walk-in (no customer attached): sum MUST equal grand_total
            // exactly — over-pay would be unredeemable, under-pay would
            // be unrecoverable.
            //
            // Customer attached: sum may be LESS than grand_total → the
            // shortfall lands on `sales.balance_due` and bumps the
            // customer's `outstanding_balance` (credit sale). Over-pay
            // is still blocked; on-account customers receive change in
            // cash or apply the surplus on a follow-up payment record,
            // not by overpaying the original ring-up.
            $sumPay = '0';
            foreach ($payments as $p) {
                $sumPay = bcadd($sumPay, (string) ($p['amount'] ?? '0'), 4);
            }
            $customerId = isset($header['customer_id']) && $header['customer_id'] !== ''
                ? (int) $header['customer_id']
                : null;

            // Tolerance: the cashier sees prices at 2dp but the server
            // stores at 4dp. The client computes grand_total in JS
            // floats; the server in bcmath (which truncates at scale 4
            // — NOT rounds). The two can disagree by a sub-paisa after
            // compound tax math, so an "exact cash" tender of ₹52.32
            // against a ₹52.3197 server-side grand would have spuriously
            // tripped the walk-in-must-be-fully-paid guard. Anything
            // bigger than a half-paisa is a real discrepancy.
            $EPSILON   = '0.05';
            $diff      = bcsub($sumPay, $grandTotal, 4);
            $isPartial = bccomp($diff, '-'.$EPSILON, 4) < 0;
            $isOver    = bccomp($diff, $EPSILON, 4) > 0;

            if ($isOver) {
                throw new RuntimeException(
                    "Payments total ({$sumPay}) is more than the grand total ({$grandTotal})."
                );
            }
            if ($isPartial && ! $customerId) {
                throw new CreditRequiresCustomer();
            }

            // Within tolerance → call it exact. Re-pin `$sumPay` to
            // the server-side grand so balance_due lands on 0 cleanly
            // instead of ±0.0001 sub-paisa.
            if (! $isPartial && ! $isOver) {
                $sumPay = (string) $grandTotal;
            }

            // Credit-limit guard. customer.credit_limit == 0 means "no
            // limit" — explicit unlimited is the default for retail
            // owners who don't want to bother configuring per-customer.
            // A positive limit blocks if the post-sale balance would
            // exceed it.
            $balanceDue = bcsub($grandTotal, $sumPay, 4);
            $customer = $customerId
                ? Customer::query()->lockForUpdate()->find($customerId)
                : null;

            if ($customer && bccomp($balanceDue, '0', 4) > 0) {
                $limit = (string) ($customer->credit_limit ?? '0');
                if (bccomp($limit, '0', 4) > 0) {
                    $newBalance = bcadd((string) ($customer->outstanding_balance ?? '0'), $balanceDue, 4);
                    if (bccomp($newBalance, $limit, 4) > 0) {
                        throw new CreditLimitExceeded(
                            customerName:   (string) $customer->name,
                            currentBalance: (string) ($customer->outstanding_balance ?? '0'),
                            newBalance:     $newBalance,
                            limit:          $limit,
                        );
                    }
                }
            }

            // ── 4. Sale row.
            $number = ($this->generateNumber)($storeId);

            // Build the row in one save. `number`, `status`, and the totals
            // are non-fillable on the model so we use forceFill — but we
            // can't split create+forceFill because `number` is NOT NULL.
            // Auto-bind the active shift, if the cashier has one open for
            // this store. Per-store enforcement (block sale when no open
            // shift) lands with the Settings → Shifts toggle in a later
            // slice — for now, shifts are opt-in and the cashier flow
            // works either way.
            $cashierId = $user?->id ?? auth()->id();
            $activeShift = $cashierId
                ? Shift::openForCashier($storeId, (int) $cashierId)
                : null;

            // Per-store shift enforcement (Slice A). When the store
            // requires shifts, a sale cannot post without an open shift —
            // unless the operator holds the `shifts.bypass_enforcement`
            // override (a manager ringing up without opening a till).
            // Offline replays follow the same rule: a sale that synced
            // after its shift closed surfaces in the sync log as failed,
            // which the owner can see and reconcile.
            if ($store->enforce_shifts && ! $activeShift) {
                $actor = $user ?? auth()->user();
                if (! ($actor?->hasPermission('shifts.bypass_enforcement') ?? false)) {
                    throw new ShiftRequired();
                }
            }

            $sale = new Sale();
            $sale->fill([
                'store_id'              => $storeId,
                // Stamp the till that rang this up (Slice B). Prefer the
                // open shift's bound terminal so the sale always agrees
                // with its shift; fall back to the request's cookie-bound
                // terminal for enforcement-off / shift-less sales.
                'terminal_id'           => $activeShift?->terminal_id ?? current_terminal_id(),
                'shift_id'              => $activeShift?->id,
                'cashier_id'            => $cashierId,
                'customer_id'           => $customerId,
                'local_uuid'            => $clientUuid,
                'sale_date'             => $header['sale_date'] ?? now()->toDateString(),
                'sale_datetime'         => now(),
                'currency_code'         => (string) ($header['currency_code'] ?? $store->currency_code),
                'exchange_rate_to_base' => '1',
                'notes'                 => $header['notes'] ?? null,
                'created_by'            => $user?->id ?? auth()->id(),
            ]);
            $sale->forceFill([
                'number'          => $number,
                'status'          => Sale::STATUS_COMPLETED,
                // Where the sale came from (cashier | kiosk | …) so reports
                // can tell counter sales from self-service ones. Defaults to
                // the DB default 'cashier' when the caller doesn't pass it.
                'origin'          => in_array(($header['origin'] ?? null), ['cashier', 'kiosk', 'api'], true)
                                        ? $header['origin'] : 'cashier',
                // Set when a kiosk order was pulled into the till for payment,
                // so the completed sale keeps the code the customer was given.
                'pickup_code'     => $header['pickup_code'] ?? null,
                'kiosk_note'      => $header['kiosk_note'] ?? null,
                'subtotal'        => $subtotal,
                'discount_total'  => $discountTotal,
                'discount_approved_by' => $discountApprovedBy,
                'tax_total'       => $taxTotal,
                'grand_total'     => $grandTotal,
                'paid_total'      => $sumPay,
                'balance_due'     => $balanceDue,
                'change_returned' => $this->changeReturned($payments),
            ]);
            $sale->save();

            // ── Order-level discount audit (Slice 3). One row per sale
            // when a discount was applied — records what the cashier
            // entered (type/value), the resolved amount, the optional
            // reason + category, and who applied it.
            if (bccomp($discountTotal, '0', 4) > 0) {
                SaleDiscount::create([
                    'sale_id'         => $sale->id,
                    'type'            => in_array(($header['discount_type'] ?? null), ['pct', 'amt'], true)
                                            ? $header['discount_type']
                                            : 'amt',
                    'value'           => $this->round4((string) ($header['discount_value'] ?? $discountTotal)),
                    'amount'          => $discountTotal,
                    'reason'          => $header['discount_reason'] ?? null,
                    'reason_category' => $header['discount_reason_category'] ?? null,
                    'applied_by'      => $cashierId,
                ]);
            }

            // Bump the customer's outstanding balance by the unpaid
            // portion. Walk-in / fully-paid sales no-op here.
            if ($customer && bccomp($balanceDue, '0', 4) > 0) {
                $customer->forceFill([
                    'outstanding_balance' => bcadd(
                        (string) ($customer->outstanding_balance ?? '0'),
                        $balanceDue,
                        4,
                    ),
                ])->save();
            }

            // ── 5. Items.
            foreach ($itemsToInsert as $row) {
                $row['sale_id'] = $sale->id;
                SaleItem::create($row);
            }

            // ── 6. Payments.
            // The payment method is the authoritative source of the gateway
            // provider — the cashier doesn't always echo it onto the row.
            $methodProviders = PaymentMethod::query()
                ->whereIn('id', array_map(fn ($p) => (int) $p['payment_method_id'], $payments))
                ->pluck('provider', 'id');

            foreach ($payments as $p) {
                $methodId    = (int) $p['payment_method_id'];
                $gwPaymentId = $p['gateway_payment_id'] ?? null;
                // Stamp the provider when this is a gateway transaction (it has
                // a gateway reference) — from the payload, else the method.
                $gwProvider  = $p['gateway_provider'] ?? null;
                if (! $gwProvider && $gwPaymentId) {
                    $gwProvider = $methodProviders[$methodId] ?? null;
                }

                SalePayment::create([
                    'sale_id'           => $sale->id,
                    'payment_method_id' => $methodId,
                    'amount'            => $this->round4((string) $p['amount']),
                    'tendered_amount'   => isset($p['tendered_amount']) ? $this->round4((string) $p['tendered_amount']) : null,
                    'change_returned'   => isset($p['change_returned']) ? $this->round4((string) $p['change_returned']) : null,
                    'reference'         => $p['reference']         ?? null,
                    'currency_code'     => (string) ($header['currency_code'] ?? $store->currency_code),
                    'gateway_provider'  => $gwProvider,
                    'gateway_payment_id'=> $gwPaymentId,
                    'gateway_signature' => $p['gateway_signature'] ?? null,
                    'gateway_status'    => $p['gateway_status']    ?? null,
                    'paid_at'           => now(),
                    'created_by'        => $user?->id ?? auth()->id(),
                ]);
            }

            // ── 7. Stock movements — one per line, negative delta.
            foreach ($sale->items()->get() as $item) {
                ($this->recordMovement)(
                    storeId:       $storeId,
                    productId:     (int) $item->product_id,
                    variantId:     $item->variant_id,
                    batchId:       $item->batch_id,
                    quantityDelta: bcmul((string) $item->quantity, '-1', 4),
                    type:          'sale',
                    referenceType: Sale::class,
                    referenceId:   (int) $sale->id,
                    unitCost:      null,          // negative movements never touch WAC
                    notes:         "Sale {$sale->number}",
                    createdBy:     $user?->id ?? auth()->id(),
                );
            }

            do_action('sale.after_complete', $sale);

            return $sale->load(['items', 'payments']);
        });
    }

    /**
     * Returns the cash-style change-returned figure: per payment row,
     * `tendered_amount - amount` (when tendered > amount). For non-cash
     * flows the row's `tendered_amount` is null and contributes zero.
     *
     * @param array<int, array<string, mixed>> $payments
     */
    private function changeReturned(array $payments): string
    {
        $change = '0';
        foreach ($payments as $p) {
            $tend = isset($p['tendered_amount']) ? (string) $p['tendered_amount'] : null;
            $amt  = (string) ($p['amount'] ?? '0');
            if ($tend !== null && bccomp($tend, $amt, 4) > 0) {
                $change = bcadd($change, bcsub($tend, $amt, 4), 4);
            }
        }
        return $change;
    }

    private function round4(string $v): string
    {
        if ($v === '' || $v === '-' || $v === '.') {
            return '0.0000';
        }
        $sign = bccomp($v, '0', 12) < 0 ? '-' : '';
        $abs  = ltrim($v, '-');
        $nudged = bcadd($abs, '0.00005', 8);
        return $sign.bcadd($nudged, '0', 4);
    }
}
