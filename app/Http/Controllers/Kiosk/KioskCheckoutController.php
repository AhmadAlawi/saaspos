<?php

namespace App\Http\Controllers\Kiosk;

use App\Actions\Inventory\ResolveFefoBatch;
use App\Actions\Products\ResolveProductPrice;
use App\Actions\Sales\CompleteSale;
use App\Actions\Sales\PriceCart;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\PosPaymentSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Kiosk `checkout` mode — the customer pays at the kiosk (Slice 3).
 *
 * Reuses the QR-chooser payment stack (`PosPaymentSession` + `/pay/pos/{uuid}`):
 *   POST /kiosk/checkout/start    — resolve prices, open a payment session,
 *                                   return the QR URL. Kiosk polls the shared
 *                                   `/cashier/pos-sessions/{uuid}/status`.
 *   POST /kiosk/checkout/complete — once the session is `paid`, finalise the
 *                                   sale through {@see CompleteSale} with
 *                                   `origin = kiosk` and return the receipt link.
 *
 * The kiosk NEVER sends prices. The authoritative sale lines are resolved at
 * `start` (same PriceCart the cashier + gateway charge use) and cached against
 * the session UUID; `complete` replays those cached lines, so the recorded
 * sale can't disagree with the amount charged, and a tampered client cart
 * can't change what's rung up. See docs/features/kiosk-self-ordering.md §5.
 */
class KioskCheckoutController extends Controller
{
    private const CACHE_PREFIX = 'kiosk:checkout:';

    public function start(Request $request, PriceCart $priceCart, ResolveProductPrice $resolvePrice, \App\Actions\Customers\ResolveKioskCustomer $resolveCustomer): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $terminal = current_terminal();
        if (! $terminal || ! $terminal->kioskEnabled()) {
            return response()->json(['message' => __('kiosk.errors.not_configured')], 422);
        }

        $data = $request->validate([
            'items'              => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'   => ['required', 'numeric', 'gt:0'],
            'local_uuid'         => ['required', 'string', 'max:64'],
            'customer_name'      => ['nullable', 'string', 'max:120'],
            'customer_phone'     => ['nullable', 'string', 'max:32'],
        ]);

        // Customer details (Slice 5) — required-mode blocks paying without a
        // name/phone; otherwise attach when provided. Resolved now and cached
        // with the lines so `complete` records it authoritatively.
        if ($terminal->kioskCustomerMode() === 'required'
            && trim((string) ($data['customer_name'] ?? '')) === ''
            && trim((string) ($data['customer_phone'] ?? '')) === '') {
            return response()->json(['message' => __('kiosk.customer.required')], 422);
        }
        $customerId = $resolveCustomer($data['customer_name'] ?? null, $data['customer_phone'] ?? null, $request->user());

        // Idempotency — a retried "Pay now" returns the existing live session
        // instead of opening a second charge for the same cart.
        $existing = PosPaymentSession::query()
            ->where('local_uuid', $data['local_uuid'])
            ->latest('id')
            ->first();
        if ($existing && ! in_array($existing->status, [PosPaymentSession::STATUS_EXPIRED, PosPaymentSession::STATUS_CANCELLED, PosPaymentSession::STATUS_FAILED], true)) {
            return response()->json($this->sessionPayload($existing));
        }

        $storeId = current_store_id() ?: default_store_id();
        $store   = Store::query()->where('is_active', true)->findOrFail((int) $storeId);

        // Resolve prices server-side and price the cart authoritatively.
        $resolved = [];
        foreach (array_values($data['items']) as $i => $raw) {
            $product = Product::query()->findOrFail((int) $raw['product_id']);
            $variant = isset($raw['variant_id']) && $raw['variant_id'] !== ''
                ? ProductVariant::query()->find((int) $raw['variant_id'])
                : null;
            $prices = $resolvePrice($product, (int) $storeId, $variant);

            // Never charge for stock we can't hand over. CompleteSale re-checks
            // under a row lock at `complete`, but that's AFTER the customer has
            // paid — so refuse up front, while it's still just a cart.
            if ($product->track_stock) {
                $available = $this->availableStock((int) $storeId, (int) $product->id, $variant?->id);
                if (bccomp((string) $raw['quantity'], $available, 4) > 0) {
                    return response()->json([
                        'message' => __('sales.errors.insufficient_stock', [
                            'name'      => (string) $product->name,
                            'available' => $available,
                            'requested' => (string) $raw['quantity'],
                        ]),
                    ], 422);
                }
            }

            $resolved[] = [
                'raw' => [
                    'product_id' => (int) $product->id,
                    'variant_id' => $variant?->id,
                    'quantity'   => (string) $raw['quantity'],
                    'unit_price' => $prices['charge_price'],
                ],
                'product' => $product,
                'index'   => $i,
            ];
        }

        $priced = $priceCart->handle($resolved, $store);

        // Narrow the scan page to the gateways the merchant enabled for THIS
        // kiosk. Empty = every configured gateway (unchanged behaviour); a
        // single id makes `/pay/pos/{uuid}` skip the chooser and jump straight
        // to that gateway. It lives on the session because the public pay page
        // has no terminal context. See docs/features/kiosk-self-ordering.md §5.
        $allowedMethodIds = $terminal->kioskPaymentMethodIds();

        $session = PosPaymentSession::create([
            'uuid'       => (string) Str::uuid(),
            'store_id'   => $store->id,
            'cashier_id' => $request->user()?->id,
            'local_uuid' => $data['local_uuid'],
            'allowed_method_ids' => $allowedMethodIds ?: null,
            'amount'     => $priced->grandTotal,
            // Currency is a SYSTEM-WIDE setting (company.base_currency_code),
            // not a per-store one — the kiosk prices, displays, and charges in
            // it, and the cashier's QR flow already sends the same value. The
            // legacy `stores.currency_code` column can still hold a stale code
            // from install; trusting it here charged the customer in one
            // currency while the kiosk showed them another.
            'currency'   => strtoupper((string) app_currency()['code']),
            'status'     => PosPaymentSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(PosPaymentSession::DEFAULT_TTL_MINUTES),
        ]);

        // Stash the authoritative lines (+ resolved customer) against the
        // session so `complete` rings up exactly what was priced — never the
        // client's resend.
        Cache::put(
            self::CACHE_PREFIX.$session->uuid,
            ['lines' => collect($resolved)->pluck('raw')->all(), 'customer_id' => $customerId],
            now()->addMinutes(PosPaymentSession::DEFAULT_TTL_MINUTES + 5),
        );

        return response()->json($this->sessionPayload($session));
    }

    public function complete(
        Request $request,
        CompleteSale $complete,
        ResolveFefoBatch $resolveFefo,
        \App\Actions\Sales\GenerateKioskPickupCode $pickupCode,
    ): JsonResponse {
        $this->authorize('create', Sale::class);

        $data = $request->validate([
            'uuid' => ['required', 'string', 'max:64'],
        ]);

        $session = PosPaymentSession::query()->where('uuid', $data['uuid'])->first();
        if (! $session) {
            return response()->json(['message' => __('kiosk.errors.checkout_expired')], 422);
        }

        // Already rung up (double-tap / retry) → return the existing receipt.
        if ($done = Sale::query()->where('local_uuid', $session->local_uuid)->first()) {
            return response()->json([
                'ok'          => true,
                'receipt_url' => $done->publicReceiptUrl(),
                'number'      => $done->number,
                'pickup_code' => $done->pickup_code,
            ]);
        }

        if (! $session->isPaid()) {
            return response()->json(['message' => __('kiosk.errors.checkout_unpaid')], 422);
        }
        if (! $session->payment_method_id) {
            return response()->json(['message' => __('kiosk.errors.checkout_unpaid')], 422);
        }

        $cached = Cache::get(self::CACHE_PREFIX.$session->uuid);
        $lines  = $cached['lines'] ?? null;
        if (empty($lines)) {
            return response()->json(['message' => __('kiosk.errors.checkout_expired')], 422);
        }

        // Auto-assign a FEFO batch for any batch-tracked line. The customer
        // can't pick a lot at a self-serve kiosk (and shouldn't — it's an
        // inventory concern), so we resolve the earliest-expiring live batch
        // server-side here, mirroring the cashier's picker without a UI. This
        // keeps per-batch quantities in step with the stock decrement — a
        // batchless line would drift the batch rows out of sync. Respects the
        // company's block-expired policy (skip expired unless the running
        // staff session holds the override).
        $company        = Company::current() ?? new Company();
        $blockExpired   = (bool) ($company->block_expired_batch_sale ?? false);
        $hasOverride    = (bool) ($request->user()?->hasPermission('inventory.sell_expired') ?? false);
        $excludeExpired = $blockExpired && ! $hasOverride;

        foreach ($lines as $i => $line) {
            $product = Product::query()->find((int) $line['product_id']);
            if (! $product || ! $product->track_batches) {
                continue;
            }
            $batch = $resolveFefo(
                (int) $session->store_id,
                (int) $line['product_id'],
                isset($line['variant_id']) && $line['variant_id'] !== '' ? (int) $line['variant_id'] : null,
                $excludeExpired,
            );
            if ($batch) {
                $lines[$i]['batch_id'] = $batch->id;
            }
        }

        $provider = PaymentMethod::query()->whereKey($session->payment_method_id)->value('provider');

        $sale = $complete(
            [
                'store_id'      => $session->store_id,
                'origin'        => 'kiosk',
                'customer_id'   => $cached['customer_id'] ?? null,
                'local_uuid'    => $session->local_uuid,
                'currency_code' => $session->currency,
                // The sale is paid, but the goods are still behind the counter.
                // The shopper needs something short to be called by — a receipt
                // QR is for their records, not for staff to match against.
                'pickup_code'   => $pickupCode(
                    (int) $session->store_id,
                    current_terminal()?->kioskPickupPrefix() ?? 'K',
                ),
            ],
            $lines,
            [[
                'payment_method_id'  => (int) $session->payment_method_id,
                'amount'             => (string) $session->amount,
                'gateway_provider'   => $provider,
                'gateway_payment_id' => $session->gateway_payment_id,
                'gateway_status'     => 'paid',
            ]],
            $request->user(),
        );

        Cache::forget(self::CACHE_PREFIX.$session->uuid);

        return response()->json([
            'ok'          => true,
            'receipt_url' => $sale->publicReceiptUrl(),
            'number'      => $sale->number,
            'pickup_code' => $sale->pickup_code,
            'grand_total' => (string) $sale->grand_total,
        ]);
    }

    /**
     * On-hand minus what held tickets / pending kiosk orders already promised,
     * for one (store, product, variant). Floored at zero. A missing stock row
     * means "never stocked" — which CompleteSale treats as zero, so we do too.
     */
    private function availableStock(int $storeId, int $productId, ?int $variantId): string
    {
        $level = \App\Models\StockLevel::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->first();

        if (! $level) {
            return '0.0000';
        }

        $available = bcsub((string) $level->quantity, (string) $level->reserved_quantity, 4);

        return bccomp($available, '0', 4) < 0 ? '0.0000' : $available;
    }

    /** @return array<string, mixed> */
    private function sessionPayload(PosPaymentSession $session): array
    {
        return [
            'uuid'       => $session->uuid,
            'pay_url'    => route('pay.pos.show', $session->uuid),
            'amount'     => (string) $session->amount,
            'currency'   => $session->currency,
            'status'     => $session->status,
            'expires_at' => optional($session->expires_at)->toIso8601String(),
        ];
    }
}
