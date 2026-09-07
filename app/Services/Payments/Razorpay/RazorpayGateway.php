<?php

namespace App\Services\Payments\Razorpay;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Razorpay implementation of the `PaymentGateway` contract.
 *
 * Per-method credentials live on `payment_methods.provider_credentials`
 * (JSON column the schema already has):
 *
 *   {
 *     "mode":           "test" | "live",
 *     "key_id":         "rzp_test_...",
 *     "key_secret":     "...",
 *     "webhook_secret": "..."
 *   }
 *
 * The async lifecycle mirrors Stripe:
 *   - startPayment()  → creates a Razorpay Payment Link, returns
 *                       short_url for the QR / chooser redirect
 *   - pollStatus()    → fetches the link to detect "paid"
 *   - handleWebhook() → verifies + normalises the event envelope
 */
class RazorpayGateway implements PaymentGateway
{
    public function __construct(
        private readonly RazorpaySignatureVerifier $verifier,
    ) {}

    public function code(): string  { return 'razorpay'; }
    public function label(): string { return __('sales.payment_methods.razorpay'); }

    /* ── Sync-tender stubs (not used for Razorpay) ────────────── */
    public function createOrder(string $amount, Sale $sale): array { return []; }
    public function verify(SalePayment $payment): bool             { return true; }

    public function refund(SalePayment $payment, string $amount): bool
    {
        $client    = $this->clientFor($payment->paymentMethod);
        $paymentId = (string) $payment->gateway_payment_id;
        if ($paymentId === '') {
            throw new RuntimeException('Cannot refund Razorpay payment: missing payment id.');
        }
        $amountMinor = $this->toMinor($amount, $payment->currency_code ?? 'inr');
        $client->createRefund($paymentId, $amountMinor);
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

        $link = $client->createPaymentLink(
            amountMinor: (int) $amountMinor,
            currency:    $currency,
            description: (string) ($context['description'] ?? __('sales.payment_methods.razorpay_default_line')),
            callbackUrl: (string) ($context['return_url'] ?? url('/cashier?razorpay_return=success')),
            notes:       ['local_uuid' => $localUuid],
        );

        return [
            'session_id' => (string) ($link['id']        ?? ''),
            'url'        => (string) ($link['short_url'] ?? ''),
            'expires_at' => isset($link['expire_by'])
                ? \date('c', (int) $link['expire_by'])
                : null,
        ];
    }

    public function pollStatus(string $sessionId, PaymentMethod $method): array
    {
        $client = $this->clientFor($method);
        $link   = $client->retrievePaymentLink($sessionId);

        // Razorpay payment_link.status ∈ {created, partially_paid, paid,
        // expired, cancelled}. Map to our normalized vocab.
        $rzpStatus = (string) ($link['status'] ?? 'created');

        $status = match ($rzpStatus) {
            'paid'                  => 'paid',
            'expired', 'cancelled'  => 'failed',
            default                 => 'pending',
        };

        // The first captured payment id lives in the `payments` array
        // on the link; we use it for refund correlation later.
        $paymentId = null;
        if (isset($link['payments']) && \is_array($link['payments'])) {
            foreach ($link['payments'] as $p) {
                if (($p['status'] ?? '') === 'captured') {
                    $paymentId = (string) ($p['payment_id'] ?? '');
                    break;
                }
            }
        }

        return [
            'status'       => $status,
            'payment_id'   => $paymentId ?: null,
            'amount_minor' => isset($link['amount_paid'])
                ? (string) $link['amount_paid']
                : null,
        ];
    }

    public function handleWebhook(Request $request, PaymentMethod $method): array
    {
        $creds         = $this->credentials($method);
        $signingSecret = (string) ($creds['webhook_secret'] ?? '');
        $signature     = (string) $request->header('X-Razorpay-Signature', '');
        $rawBody       = (string) $request->getContent();

        $this->verifier->verify($rawBody, $signature, $signingSecret);

        $event = \json_decode($rawBody, true) ?: [];
        $type  = (string) ($event['event'] ?? '');

        // Razorpay webhook envelope: payload.{entity}.entity → the
        // affected object. For payment_link.* events, that's the
        // payment_link entity. For payment.captured (an alternate
        // signal), payload.payment.entity is the payment.
        $linkEntity    = $event['payload']['payment_link']['entity'] ?? [];
        $paymentEntity = $event['payload']['payment']['entity']      ?? [];

        $sessionId = (string) ($linkEntity['id'] ?? $paymentEntity['payment_link_id'] ?? '') ?: null;
        $paymentId = (string) ($paymentEntity['id'] ?? '') ?: null;
        if (!$paymentId && \is_array($linkEntity['payments'] ?? null)) {
            foreach ($linkEntity['payments'] as $p) {
                if (($p['status'] ?? '') === 'captured') {
                    $paymentId = (string) ($p['payment_id'] ?? '') ?: null;
                    break;
                }
            }
        }

        return [
            'event_id'     => (string) ($event['id'] ?? \sha1($rawBody)),
            'event_type'   => $type,
            'session_id'   => $sessionId,
            'payment_id'   => $paymentId,
            'status'       => (string) ($linkEntity['status'] ?? $paymentEntity['status'] ?? '') ?: null,
            'amount_minor' => isset($linkEntity['amount_paid'])
                ? (string) $linkEntity['amount_paid']
                : (isset($paymentEntity['amount'])
                    ? (string) $paymentEntity['amount']
                    : null),
            'raw'          => $event,
        ];
    }

    /* ── Helpers ──────────────────────────────────────────────── */

    /** Build a `RazorpayClient` from the payment method's stored credentials. */
    private function clientFor(?PaymentMethod $method): RazorpayClient
    {
        if (!$method) {
            throw new RuntimeException('Razorpay gateway: no payment method context.');
        }
        $creds = $this->credentials($method);
        return new RazorpayClient(
            keyId:     (string) ($creds['key_id']     ?? ''),
            keySecret: (string) ($creds['key_secret'] ?? ''),
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
     * Convert a decimal-string amount to Razorpay's minor unit.
     * Mirrors StripeGateway::toMinor() — same currency conventions.
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
