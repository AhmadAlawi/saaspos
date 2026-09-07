<?php

namespace App\Http\Controllers\Cashier;

use App\Actions\Sales\PriceCart;
use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\PosPaymentSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Support\PricedCart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cashier-side endpoints for the QR-chooser payment flow.
 *
 *   POST  /cashier/pos-sessions          — create a session for this cart
 *   GET   /cashier/pos-sessions/{uuid}/status — cashier polls every 2s
 *   POST  /cashier/pos-sessions/{uuid}/cancel — "Cancel & switch to cash"
 *
 * The QR the cashier shows points at `/pay/pos/{uuid}` on our own
 * domain. The customer picks a gateway there; we round-trip; the
 * session row's status is what the cashier polls.
 */
class PaymentSessionController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $data = $request->validate([
            'amount'     => ['required', 'string'],
            'currency'   => ['required', 'string', 'size:3'],
            'local_uuid' => ['required', 'string', 'max:64'],
            // Cart snapshot so the customer's pay page can show the full
            // breakdown. Customer-facing only — names + numbers, no
            // internal IDs. Capped so a runaway cart can't bloat the row;
            // exact fields are allow-listed in normalizeCartSummary().
            'cart'        => ['sometimes', 'array'],
            'cart.lines'  => ['sometimes', 'array', 'max:200'],
            'cart.totals' => ['sometimes', 'array'],
            // Authoritative-pricing inputs: the actual sale lines (so the
            // server prices the cart itself) plus whatever's already been
            // tendered (split-tender cash committed before this QR). The
            // charged amount is then computed server-side, NOT trusted
            // from the client estimate — see below.
            'items'                  => ['sometimes', 'array', 'max:200'],
            'items.*.product_id'     => ['required_with:items', 'integer'],
            'items.*.quantity'       => ['required_with:items', 'string'],
            'items.*.unit_price'     => ['required_with:items', 'string'],
            'items.*.discount_amount'=> ['sometimes', 'nullable', 'string'],
            'paid_already'           => ['sometimes', 'string'],
            // Set only by the wallet buttons (Apple Pay / Google Pay) —
            // stamps the session's allow-list to that one gateway so
            // CustomerPayController::show() auto-skips the chooser and
            // forwards the customer straight to Stripe Checkout. Omitted
            // (or null) for the generic "Charge via QR" tile, which still
            // lets the customer pick any configured gateway.
            'payment_method_id'      => ['sometimes', 'nullable', 'integer', 'exists:payment_methods,id'],
            // Which wallet button the cashier tapped — tells
            // CustomerPayController::show() to render the minimal Payment
            // Request Button page instead of redirecting to Stripe's
            // hosted Checkout. Null for the generic QR tile.
            'wallet_brand'           => ['sometimes', 'nullable', 'string', 'in:apple_pay,google_pay'],
        ]);

        $allowedMethodIds = null;
        if (! empty($data['payment_method_id'])) {
            $method = PaymentMethod::query()->find($data['payment_method_id']);
            if (! $method || ! $method->is_active || empty($method->provider)) {
                return response()->json(['message' => 'That payment method is not available.'], 422);
            }
            $allowedMethodIds = [$method->id];
        }

        $storeId = (int) $request->session()->get('active_store_id', 0)
            ?: \App\Models\Store::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->value('id');

        $cartSummary = $this->normalizeCartSummary($data['cart'] ?? []);

        // ── Server-authoritative amount. The client's `amount` is a
        // display estimate computed with simplified JS tax math; trusting
        // it for the actual gateway charge is exactly what let the gateway
        // charge one number while CompleteSale recorded another. When the
        // sale lines are supplied, we re-price the cart with the SAME
        // PriceCart action CompleteSale uses, so charge == record. Falls
        // back to the client estimate only when lines are absent.
        $amount = $data['amount'];
        if (! empty($data['items'])) {
            $priced = $this->priceLines($data['items'], (int) $storeId);
            if ($priced !== null) {
                $paidAlready = (string) ($data['paid_already'] ?? '0');
                $remaining   = bcsub($priced->grandTotal, $paidAlready, 4);
                // Never charge below zero (over-tendered before the QR).
                $amount = bccomp($remaining, '0', 4) > 0 ? $remaining : '0.0000';
            }
        }

        $session = PosPaymentSession::create([
            'uuid'         => (string) Str::uuid(),
            'store_id'     => $storeId,
            'cashier_id'   => $request->user()?->id,
            'local_uuid'   => $data['local_uuid'],
            'amount'       => $amount,
            'currency'     => strtoupper($data['currency']),
            'cart_summary' => $cartSummary ?: null,
            'status'       => PosPaymentSession::STATUS_PENDING,
            'expires_at'   => now()->addMinutes(PosPaymentSession::DEFAULT_TTL_MINUTES),
            'allowed_method_ids' => $allowedMethodIds,
            'wallet_brand' => $data['wallet_brand'] ?? null,
        ]);

        return response()->json([
            'uuid'        => $session->uuid,
            'pay_url'     => route('pay.pos.show', $session->uuid),
            'amount'      => (string) $session->amount,
            'currency'    => $session->currency,
            'expires_at'  => optional($session->expires_at)->toIso8601String(),
            'status'      => $session->status,
        ]);
    }

    /**
     * Price the supplied sale lines with the SAME PriceCart action that
     * CompleteSale uses, so the charge amount is byte-for-byte what the
     * sale will record. Returns null if the store or any product can't
     * be resolved — the caller then falls back to the client estimate.
     *
     * @param array<int, array<string, mixed>> $items
     */
    private function priceLines(array $items, int $storeId): ?PricedCart
    {
        $store = Store::query()->find($storeId);
        if (! $store) {
            return null;
        }

        $resolved = [];
        foreach (array_values($items) as $i => $raw) {
            $product = Product::query()->find((int) ($raw['product_id'] ?? 0));
            if (! $product) {
                return null;   // unknown product → can't price authoritatively
            }
            $resolved[] = ['raw' => $raw, 'product' => $product, 'index' => $i];
        }

        if (empty($resolved)) {
            return null;
        }

        return app(PriceCart::class)->handle($resolved, $store);
    }

    /**
     * Allow-list + cap the cart snapshot the cashier posts. The cashier
     * is trusted, but this is display-only data persisted on the row, so
     * we keep only the known fields, force strings/bools, and bound the
     * line + kit counts. Returns null for an empty cart (no card shown).
     *
     * @param  array<string, mixed>  $cart
     * @return array{lines: array<int, array<string, mixed>>, totals: array<string, mixed>}|null
     */
    private function normalizeCartSummary(array $cart): ?array
    {
        $lines = collect($cart['lines'] ?? [])
            ->take(200)
            ->map(fn ($l) => [
                'name'          => (string) ($l['name'] ?? ''),
                'quantity'      => (string) ($l['quantity'] ?? ''),
                'unit_price'    => (string) ($l['unit_price'] ?? '0'),
                'line_total'    => (string) ($l['line_total'] ?? '0'),
                'tax_rate'      => (string) ($l['tax_rate'] ?? '0'),
                'tax_amount'    => (string) ($l['tax_amount'] ?? '0'),
                'tax_inclusive' => (bool) ($l['tax_inclusive'] ?? false),
                'tax_taxable'   => (bool) ($l['tax_taxable'] ?? true),
                'kit_items'     => collect($l['kit_items'] ?? [])
                    ->take(100)
                    ->map(fn ($k) => [
                        'name'          => (string) ($k['name'] ?? ''),
                        'quantity'      => (string) ($k['quantity'] ?? ''),
                        'variant_label' => isset($k['variant_label']) ? (string) $k['variant_label'] : null,
                    ])
                    ->values()->all(),
            ])
            ->values()->all();

        if (empty($lines)) {
            return null;
        }

        $totals = $cart['totals'] ?? [];

        return [
            'lines'  => $lines,
            'totals' => [
                'subtotal'        => (string) ($totals['subtotal'] ?? '0'),
                'discount_type'   => isset($totals['discount_type']) ? (string) $totals['discount_type'] : null,
                'discount_value'  => isset($totals['discount_value']) ? (string) $totals['discount_value'] : null,
                'discount_amount' => (string) ($totals['discount_amount'] ?? '0'),
                'tax_total'       => (string) ($totals['tax_total'] ?? '0'),
                'grand_total'     => (string) ($totals['grand_total'] ?? '0'),
            ],
        ];
    }

    public function status(Request $request, string $uuid, \App\Actions\Payments\ReconcilePosPaymentSession $reconcile): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $session = PosPaymentSession::query()->where('uuid', $uuid)->first();
        if (! $session) {
            return response()->json(['status' => 'expired'], 200);
        }

        // Actively confirm with the gateway before we answer. Without this the
        // status is only as fresh as the webhook / return-redirect, which a
        // self-hosted install often can't rely on — so a paid session could sit
        // in `selected` and the till/kiosk would time it out as "not paid".
        $reconcile($session);

        // Lazy expiry — flip the row if TTL has elapsed without payment.
        if ($session->isExpired()) {
            $session->forceFill(['status' => PosPaymentSession::STATUS_EXPIRED])->save();
        }

        return response()->json([
            'uuid'          => $session->uuid,
            'status'        => $session->status,
            'gateway_payment_id' => $session->gateway_payment_id,
            'payment_method_id'  => $session->payment_method_id,
            'paid_at'       => optional($session->paid_at)->toIso8601String(),
            'failure_message' => $session->failure_message,
        ]);
    }

    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $this->authorize('create', Sale::class);

        DB::transaction(function () use ($uuid) {
            $session = PosPaymentSession::query()
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->first();
            if (! $session) return;
            if ($session->isTerminal()) return;     // already paid/expired/cancelled — no-op

            $session->forceFill([
                'status'       => PosPaymentSession::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ])->save();
        });

        return response()->json(['ok' => true]);
    }
}
