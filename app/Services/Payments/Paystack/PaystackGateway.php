<?php

namespace App\Services\Payments\Paystack;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Paystack implementation of the `PaymentGateway` contract.
 *
 * Per-method credentials live on `payment_methods.provider_credentials`
 * (JSON column the schema already has):
 *
 *   {
 *     "mode":           "test" | "live",
 *     "public_key":     "pk_test_..." | "pk_live_...",
 *     "secret_key":     "sk_test_..." | "sk_live_..."
 *   }
 *
 * There is NO separate webhook secret — Paystack signs webhooks with
 * the same secret_key, so the form has one fewer field than Stripe.
 *
 * The async lifecycle mirrors Stripe / Razorpay:
 *   - startPayment()  → initialises a Paystack transaction, returns
 *                       the hosted authorization_url
 *   - pollStatus()    → verifies the transaction reference
 *   - handleWebhook() → verifies + normalises the event envelope
 */
class PaystackGateway implements PaymentGateway
{
    public function __construct(
        private readonly PaystackSignatureVerifier $verifier,
    ) {}

    public function code(): string  { return 'paystack'; }
    public function label(): string { return __('sales.payment_methods.paystack'); }

    /* ── Sync-tender stubs (not used for Paystack) ────────────── */
    public function createOrder(string $amount, Sale $sale): array { return []; }
    public function verify(SalePayment $payment): bool             { return true; }

    public function refund(SalePayment $payment, string $amount): bool
    {
        $client    = $this->clientFor($payment->paymentMethod);
        $reference = (string) $payment->gateway_payment_id;
        if ($reference === '') {
            throw new RuntimeException('Cannot refund Paystack payment: missing transaction reference.');
        }
        $amountMinor = $this->toMinor($amount, $payment->currency_code ?? 'ngn');
        $client->createRefund($reference, $amountMinor);
        return true;
    }

    /* ── Async lifecycle ──────────────────────────────────────── */

    public function startPayment(string $amountMinor, string $currency, array $context): array
    {
        /** @var PaymentMethod $method */
        $method = $context['payment_method'];
        $client = $this->clientFor($method);

        $localUuid = (string) ($context['local_uuid'] ?? '');
        if ($localUuid === '') {
            throw new RuntimeException('startPayment requires `local_uuid` in context.');
        }

        // Paystack requires an email on every transaction. For walk-in
        // POS sales we don't have one — synthesize a placeholder.
        // Fall back to `example.com` if APP_URL is misconfigured so
        // Paystack still accepts the request (it just validates email
        // *shape*, not deliverability).
        $email = (string) ($context['email'] ?? $this->placeholderEmail($localUuid));

        // Paystack treats `reference` as the idempotency key + lookup
        // id — reuse the session uuid so polling/return/webhook all
        // share the same handle.
        $reference = $localUuid;

        $txn = $client->initializeTransaction(
            amountMinor: (int) $amountMinor,
            currency:    $currency,
            email:       $email,
            reference:   $reference,
            callbackUrl: (string) ($context['return_url'] ?? url('/cashier?paystack_return=success')),
            metadata:    ['local_uuid' => $localUuid],
        );

        return [
            'session_id' => (string) ($txn['reference']         ?? $reference),
            'url'        => (string) ($txn['authorization_url'] ?? ''),
            'expires_at' => null,        // Paystack does not expose an expiry on init
        ];
    }

    public function pollStatus(string $sessionId, PaymentMethod $method): array
    {
        $client = $this->clientFor($method);
        $txn    = $client->verifyTransaction($sessionId);

        // Paystack transaction.status ∈ {success, failed, abandoned,
        // ongoing, pending, processing, queued, reversed}. Map to our
        // normalized vocab.
        $paystackStatus = (string) ($txn['status'] ?? 'pending');

        $status = match ($paystackStatus) {
            'success'                          => 'paid',
            'failed', 'abandoned', 'reversed'  => 'failed',
            default                            => 'pending',
        };

        return [
            'status'       => $status,
            'payment_id'   => (string) ($txn['reference'] ?? '') ?: null,
            'amount_minor' => isset($txn['amount']) ? (string) $txn['amount'] : null,
        ];
    }

    public function handleWebhook(Request $request, PaymentMethod $method): array
    {
        $creds         = $this->credentials($method);
        // Paystack signs with secret_key — there is no separate
        // webhook secret to configure.
        $signingSecret = (string) ($creds['secret_key'] ?? '');
        $signature     = (string) $request->header('X-Paystack-Signature', '');
        $rawBody       = (string) $request->getContent();

        $this->verifier->verify($rawBody, $signature, $signingSecret);

        $event = \json_decode($rawBody, true) ?: [];
        $type  = (string) ($event['event'] ?? '');
        $data  = $event['data'] ?? [];

        return [
            'event_id'     => (string) ($data['id'] ?? \sha1($rawBody)),
            'event_type'   => $type,
            'session_id'   => (string) ($data['reference'] ?? '') ?: null,
            'payment_id'   => (string) ($data['reference'] ?? '') ?: null,
            'status'       => (string) ($data['status']    ?? '') ?: null,
            'amount_minor' => isset($data['amount']) ? (string) $data['amount'] : null,
            'raw'          => $event,
        ];
    }

    /* ── Helpers ──────────────────────────────────────────────── */

    /**
     * Synthesize a deliverable-looking placeholder email tied to the
     * app host. Falls back to `example.com` (a reserved, always-valid
     * domain) if APP_URL has no parseable host — Paystack only
     * validates email shape, so this keeps the call from 500'ing
     * just because the operator didn't set APP_URL with a scheme.
     */
    private function placeholderEmail(string $localUuid): string
    {
        $host = parse_url((string) url('/'), PHP_URL_HOST) ?: 'example.com';
        $shortId = substr(str_replace('-', '', $localUuid), 0, 8);
        return 'pos+' . $shortId . '@' . $host;
    }

    /** Build a `PaystackClient` from the payment method's stored credentials. */
    private function clientFor(?PaymentMethod $method): PaystackClient
    {
        if (!$method) {
            throw new RuntimeException('Paystack gateway: no payment method context.');
        }
        $creds = $this->credentials($method);
        return new PaystackClient(
            secretKey: (string) ($creds['secret_key'] ?? ''),
        );
    }

    /** Returns the decoded provider_credentials JSON, or []. */
    private function credentials(PaymentMethod $method): array
    {
        $raw = $method->provider_credentials;
        if (\is_array($raw))  return $raw;
        if (\is_string($raw)) return \json_decode($raw, true) ?: [];
        return [];
    }

    /**
     * Convert a decimal-string amount to Paystack's minor unit.
     * Same shape as Stripe/Razorpay — Paystack's supported currencies
     * (NGN, GHS, ZAR, KES, USD) are all 2-decimal, so the broader
     * zero/three-decimal table never trips, but we keep it consistent
     * so the helper looks identical across providers.
     */
    private function toMinor(string $amount, string $currency): int
    {
        $decimals = $this->decimalsFor($currency);
        $scaled   = \bcmul($amount, \bcpow('10', (string) $decimals, 0), 0);
        return (int) $scaled;
    }

    private function decimalsFor(string $currency): int
    {
        $zero  = ['JPY', 'KRW', 'VND', 'CLP', 'IDR'];
        $three = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];
        $upper = \strtoupper($currency);
        if (\in_array($upper, $zero,  true)) return 0;
        if (\in_array($upper, $three, true)) return 3;
        return 2;
    }
}
