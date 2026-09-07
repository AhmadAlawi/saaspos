<?php

namespace App\Services\Payments\Flutterwave;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Flutterwave implementation of the `PaymentGateway` contract.
 *
 * Per-method credentials live on `payment_methods.provider_credentials`:
 *
 *   {
 *     "mode":            "test" | "live",
 *     "public_key":      "FLWPUBK_TEST-..." | "FLWPUBK-...",
 *     "secret_key":      "FLWSECK_TEST-..." | "FLWSECK-...",
 *     "webhook_secret":  "free-form merchant-chosen secret hash"
 *   }
 *
 * Two key divergences from the other gateways:
 *   - Flutterwave's API takes WHOLE-unit amounts (₦100 = "100"),
 *     not minor units. `startPayment` converts back.
 *   - Webhook auth is a static shared-secret compare (no HMAC) —
 *     see FlutterwaveSignatureVerifier.
 */
class FlutterwaveGateway implements PaymentGateway
{
    public function __construct(
        private readonly FlutterwaveSignatureVerifier $verifier,
    ) {}

    public function code(): string  { return 'flutterwave'; }
    public function label(): string { return __('sales.payment_methods.flutterwave'); }

    /* ── Sync-tender stubs (not used for Flutterwave) ─────────── */
    public function createOrder(string $amount, Sale $sale): array { return []; }
    public function verify(SalePayment $payment): bool             { return true; }

    public function refund(SalePayment $payment, string $amount): bool
    {
        $client = $this->clientFor($payment->paymentMethod);
        // We store Flutterwave's numeric `id` (not our tx_ref) as
        // `gateway_payment_id` on capture — the refund endpoint
        // takes that numeric id in the path.
        $transactionId = (int) ($payment->gateway_payment_id ?? 0);
        if ($transactionId <= 0) {
            throw new RuntimeException('Cannot refund Flutterwave payment: missing transaction id.');
        }

        // Our money is DECIMAL(15,4), but Flutterwave wants whole units at
        // the currency's precision. Normalise the SAME way the charge was
        // derived (truncate to the currency's minor units, then back) so the
        // amount is valid (no 4-decimal values) and never exceeds what was
        // captured — e.g. 5.0675 → 5.06, matching the original charge.
        $currency = (string) ($payment->currency_code ?? 'NGN');
        $amount   = $this->minorToWhole($this->wholeToMinor($amount, $currency), $currency);

        $client->createRefund($transactionId, $amount);

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

        // Flutterwave wants the customer block; we synthesize a
        // placeholder email tied to the host (same pattern as
        // Paystack) so walk-in sales succeed without a real email.
        $email = (string) ($context['email'] ?? $this->placeholderEmail($localUuid));

        // Convert minor → whole units for the wire. Currency-aware
        // exponent so JPY/KWD/etc. stay correct.
        $amountWhole = $this->minorToWhole($amountMinor, $currency);

        $data = $client->createCharge(
            amount:      $amountWhole,
            currency:    $currency,
            txRef:       $localUuid,
            redirectUrl: (string) ($context['return_url'] ?? url('/cashier?flutterwave_return=success')),
            title:       (string) ($context['description'] ?? __('sales.payment_methods.flutterwave_default_line')),
            customer:    ['email' => $email],
            meta:        ['local_uuid' => $localUuid],
        );

        return [
            'session_id' => $localUuid,                            // we own the reference
            'url'        => (string) ($data['link'] ?? ''),
            'expires_at' => null,                                  // not exposed on /v3/payments
        ];
    }

    public function pollStatus(string $sessionId, PaymentMethod $method): array
    {
        $client = $this->clientFor($method);
        $txn    = $client->verifyByReference($sessionId);

        // Flutterwave transaction.status ∈ {successful, failed,
        // pending, cancelled}. Map to our normalized vocab.
        $flwStatus = (string) ($txn['status'] ?? 'pending');

        $status = match ($flwStatus) {
            'successful'         => 'paid',
            'failed', 'cancelled'=> 'failed',
            default              => 'pending',
        };

        return [
            // We promote Flutterwave's numeric `id` to `payment_id`
            // so the refund endpoint has what it needs later.
            'status'       => $status,
            'payment_id'   => isset($txn['id']) ? (string) $txn['id'] : null,
            'amount_minor' => isset($txn['amount'])
                ? $this->wholeToMinor((string) $txn['amount'], (string) ($txn['currency'] ?? 'USD'))
                : null,
        ];
    }

    public function handleWebhook(Request $request, PaymentMethod $method): array
    {
        $creds         = $this->credentials($method);
        $signingSecret = (string) ($creds['webhook_secret'] ?? '');
        // Flutterwave's header name is exactly `verif-hash`
        // (lowercase, hyphenated). Laravel normalises so the lookup
        // below is case-insensitive.
        $signature     = (string) $request->header('verif-hash', '');

        $this->verifier->verify($signature, $signingSecret);

        $rawBody = (string) $request->getContent();
        $event   = \json_decode($rawBody, true) ?: [];
        $type    = (string) ($event['event'] ?? '');
        $data    = $event['data'] ?? [];

        return [
            'event_id'     => (string) ($data['id'] ?? \sha1($rawBody)),
            'event_type'   => $type,
            // tx_ref is our handle; id is Flutterwave's numeric handle
            'session_id'   => (string) ($data['tx_ref'] ?? '') ?: null,
            'payment_id'   => isset($data['id']) ? (string) $data['id'] : null,
            'status'       => (string) ($data['status'] ?? '') ?: null,
            'amount_minor' => isset($data['amount'])
                ? $this->wholeToMinor((string) $data['amount'], (string) ($data['currency'] ?? 'USD'))
                : null,
            'raw'          => $event,
        ];
    }

    /* ── Helpers ──────────────────────────────────────────────── */

    /**
     * Synthesize a deliverable-looking placeholder email tied to the
     * app host. Falls back to `example.com` (a reserved, always-valid
     * domain) if APP_URL has no parseable host.
     */
    private function placeholderEmail(string $localUuid): string
    {
        $host = parse_url((string) url('/'), PHP_URL_HOST) ?: 'example.com';
        $shortId = substr(str_replace('-', '', $localUuid), 0, 8);
        return 'pos+' . $shortId . '@' . $host;
    }

    /** Build a `FlutterwaveClient` from the payment method's stored credentials. */
    private function clientFor(?PaymentMethod $method): FlutterwaveClient
    {
        if (!$method) {
            throw new RuntimeException('Flutterwave gateway: no payment method context.');
        }
        $creds = $this->credentials($method);
        return new FlutterwaveClient(
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
     * Minor units (the interface contract) → whole-unit decimal
     * string for the Flutterwave wire format.
     *
     * Inverse of the toMinor helpers on the other gateways.
     */
    private function minorToWhole(string $amountMinor, string $currency): string
    {
        $decimals = $this->decimalsFor($currency);
        if ($decimals === 0) {
            // Zero-decimal currencies: 1000 → "1000".
            return (string) (int) $amountMinor;
        }
        return \bcdiv($amountMinor, \bcpow('10', (string) $decimals, 0), $decimals);
    }

    /** Whole-unit string → minor units (string). Used when echoing
     *  amounts from Flutterwave's payloads back through our system. */
    private function wholeToMinor(string $amountWhole, string $currency): string
    {
        $decimals = $this->decimalsFor($currency);
        return \bcmul($amountWhole, \bcpow('10', (string) $decimals, 0), 0);
    }

    private function decimalsFor(string $currency): int
    {
        $zero  = ['JPY', 'KRW', 'VND', 'CLP', 'IDR', 'UGX', 'RWF', 'MWK', 'XAF', 'XOF'];
        $three = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];
        $upper = \strtoupper($currency);
        if (\in_array($upper, $zero,  true)) return 0;
        if (\in_array($upper, $three, true)) return 3;
        return 2;
    }
}
