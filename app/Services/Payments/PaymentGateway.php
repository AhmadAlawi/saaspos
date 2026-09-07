<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\Request;

/**
 * Strategy contract for every payment-tender pathway — cash, manual-reference
 * card-terminal, Stripe, Razorpay, Flutterwave, store-credit, etc.
 *
 * Slice 1 only implements the synchronous tenders (cash + manual reference)
 * — money is in hand by the time the sale completes. Slice 2 adds the
 * async gateway tenders (Stripe / Razorpay / Flutterwave) which require:
 *   - `createOrder()` to spin up a gateway intent and get a QR / link
 *   - the cashier polls or listens for a webhook signalling success
 *   - then `complete()` is called and the sale persists
 *
 * The action layer ({@see App\Actions\Sales\CompleteSale}) treats payment
 * rows as opaque shape — it doesn't branch per-gateway. The gateway is
 * picked AT the cashier screen and is responsible for the populated
 * shape (gateway_payment_id, gateway_status, reference, etc.) that
 * persists into `sale_payments`.
 */
interface PaymentGateway
{
    /** Programmatic key matching `payment_methods.code` (e.g. 'cash', 'card_manual'). */
    public function code(): string;

    /** Translated display name for the cashier tile. */
    public function label(): string;

    /**
     * Slice 2 hook — create a gateway-side order/intent before the customer
     * pays. Synchronous tenders (cash, manual-reference) leave this empty
     * and return immediately. Async tenders return an array with the QR /
     * link / intent id the cashier needs to display.
     *
     * @return array<string, mixed>
     */
    public function createOrder(string $amount, Sale $sale): array;

    /**
     * Slice 2 hook — verify the gateway-side payment status before the
     * sale row is finalised. Returns true when the gateway confirms the
     * amount has been captured. Synchronous tenders always return true.
     */
    public function verify(SalePayment $payment): bool;

    /**
     * Slice 4 hook (return slice) — refund the captured amount through
     * the same gateway. Returns true on success. Synchronous tenders
     * record a paper trail and return true.
     */
    public function refund(SalePayment $payment, string $amount): bool;

    /* ── Async gateway lifecycle (Stripe, Razorpay, Paystack, ...) ──
     *
     * The cashier flow for async gateways is:
     *   1. cashier picks the gateway tile → POST to /cashier/gateways/{code}/start
     *      with the cart amount + currency + a client-generated reference
     *      (local_uuid).
     *   2. The controller calls `startPayment()` on this gateway and
     *      gets back `{session_id, url, expires_at}` — the URL becomes
     *      a QR + tappable link in the cashier modal.
     *   3. Customer pays on the gateway-hosted page.
     *   4. The cashier UI polls /cashier/gateways/{code}/status — the
     *      controller calls `pollStatus()` and forwards the answer.
     *   5. When `pollStatus` returns `paid`, the cashier auto-completes
     *      the sale via the existing /cashier/complete flow with
     *      gateway_provider=<code>, gateway_payment_id=<session_id>.
     *   6. The gateway's webhook lands at /webhooks/{code} — the
     *      controller calls `handleWebhook()` to verify the signature
     *      and extract event data; webhook is for audit + edge-case
     *      reconciliation (cashier connection drop mid-payment), not
     *      the primary path. */

    /**
     * Start a hosted payment session. Returns the URL the customer
     * pays on + a stable session id we can store and poll later.
     *
     * `$amountMinor` is the smallest currency unit (cents, paise, etc.).
     * The implementation converts to the gateway's expected format.
     *
     * `$context` carries cashier-side identifiers: `local_uuid` (the
     * idempotency key the cashier already mints for the cart),
     * optional `description`, and the active `payment_method` row
     * (provider credentials live on it).
     *
     * @param  array{
     *     local_uuid: string,
     *     description?: string,
     *     payment_method: PaymentMethod,
     *     return_url?: string,
     * }  $context
     * @return array{session_id: string, url: string, expires_at?: string|null}
     */
    public function startPayment(string $amountMinor, string $currency, array $context): array;

    /**
     * Look up the current state of a previously-started session.
     *
     * `status` values:
     *   - `pending`  — session created, customer hasn't paid yet
     *   - `paid`     — gateway confirms the charge succeeded
     *   - `failed`   — customer attempted and failed, or session expired
     *   - `unknown`  — caller should retry shortly (transient error)
     *
     * @return array{
     *     status: 'pending'|'paid'|'failed'|'unknown',
     *     payment_id?: string|null,
     *     amount_minor?: string|null,
     * }
     */
    public function pollStatus(string $sessionId, PaymentMethod $method): array;

    /**
     * Verify + parse an incoming webhook. Throws on signature failure
     * so the controller can return 400. Returns a normalized event
     * envelope the controller can log and act on without needing
     * gateway-specific knowledge.
     *
     * @return array{
     *     event_id: string,
     *     event_type: string,
     *     session_id?: string|null,
     *     payment_id?: string|null,
     *     status?: string|null,
     *     amount_minor?: string|null,
     *     raw: array<string, mixed>,
     * }
     */
    public function handleWebhook(Request $request, PaymentMethod $method): array;
}
