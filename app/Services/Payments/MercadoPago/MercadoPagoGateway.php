<?php

namespace App\Services\Payments\MercadoPago;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Mercado Pago implementation of the `PaymentGateway` contract.
 *
 * Per-method credentials live on `payment_methods.provider_credentials`:
 *
 *   {
 *     "mode":           "test" | "live",
 *     "public_key":     "TEST-..." | "APP_USR-...",
 *     "access_token":   "TEST-..." | "APP_USR-...",
 *     "webhook_secret": "..."
 *   }
 *
 * Three divergences worth noting:
 *   - `access_token` (Mercado Pago's name) is the secret used for
 *     both REST auth and webhook HMAC. We keep their naming so
 *     copy-pasting from their dashboard is friction-free.
 *   - The hosted checkout has TWO URLs in the response —
 *     `init_point` (live) and `sandbox_init_point` (test). We pick
 *     based on `mode`.
 *   - Status doesn't live on the preference; we have to call the
 *     payments-search endpoint by external_reference to poll.
 */
class MercadoPagoGateway implements PaymentGateway
{
    public function __construct(
        private readonly MercadoPagoSignatureVerifier $verifier,
    ) {}

    public function code(): string  { return 'mercado_pago'; }
    public function label(): string { return __('sales.payment_methods.mercado_pago'); }

    /* ── Sync-tender stubs (not used for Mercado Pago) ────────── */
    public function createOrder(string $amount, Sale $sale): array { return []; }
    public function verify(SalePayment $payment): bool             { return true; }

    public function refund(SalePayment $payment, string $amount): bool
    {
        $client    = $this->clientFor($payment->paymentMethod);
        $paymentId = (string) $payment->gateway_payment_id;
        if ($paymentId === '') {
            throw new RuntimeException('Cannot refund Mercado Pago payment: missing payment id.');
        }

        // Normalise our DECIMAL(15,4) money to the currency's precision the
        // same way the charge was derived, so we never send a 4-decimal
        // amount or ask to refund more than was captured (e.g. 5.0675 → 5.06).
        $currency = (string) ($payment->currency_code ?? 'USD');
        $amount   = $this->minorToWhole($this->wholeToMinor($amount, $currency), $currency);

        $client->refundPayment($paymentId, $amount);

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

        // Mercado Pago wants whole-unit `unit_price` (same as
        // Flutterwave) — convert from our minor-units contract.
        $unitPrice = $this->minorToWhole($amountMinor, $currency);
        $returnUrl = (string) ($context['return_url'] ?? url('/cashier?mp_return=success'));

        $preference = $client->createPreference(
            title:             (string) ($context['description'] ?? __('sales.payment_methods.mercado_pago_default_line')),
            unitPrice:         $unitPrice,
            currencyId:        $currency,
            externalReference: $localUuid,
            backUrls:          [
                'success' => $returnUrl,
                'pending' => $returnUrl,
                'failure' => $returnUrl,
            ],
            // Notification URL — Mercado Pago will POST every
            // payment.updated here. Use the named route, not the
            // return URL.
            notificationUrl:   route('webhooks.mercado_pago'),
            metadata:          ['local_uuid' => $localUuid],
        );

        // Pick sandbox vs production checkout URL based on configured
        // mode. Sandbox lets test cards work end-to-end without
        // running real money through.
        $mode = (string) ($this->credentials($method)['mode'] ?? 'test');
        $url  = $mode === 'live'
            ? (string) ($preference['init_point']         ?? '')
            : (string) ($preference['sandbox_init_point'] ?? $preference['init_point'] ?? '');

        // We return the external_reference (= local_uuid) as our
        // session_id so that polling (search by external_reference)
        // and the webhook (which resolves to the same value through
        // the retrieved payment) all correlate cleanly off one key.
        // The Mercado Pago preference id is not needed downstream.
        return [
            'session_id' => $localUuid,
            'url'        => $url,
            'expires_at' => isset($preference['expiration_date_to'])
                ? (string) $preference['expiration_date_to']
                : null,
        ];
    }

    public function pollStatus(string $sessionId, PaymentMethod $method): array
    {
        $client = $this->clientFor($method);

        // The preference id doesn't carry payment status — search
        // by external_reference (which is our session uuid; the
        // cashier-side flow stores it as `gateway_session_id`).
        // The session uuid was stamped onto the cashier-side row;
        // the cashier-side controller looks up the session and
        // passes the gateway_session_id (preference id) here. For
        // mercado pago we need the external_reference — store it
        // when creating the preference. For now we accept that the
        // caller may pass either; if it doesn't look like a
        // preference id, treat it as the external_reference.
        $payment = $client->findPaymentByExternalReference($sessionId);

        if (!$payment) {
            return ['status' => 'pending', 'payment_id' => null, 'amount_minor' => null];
        }

        // Mercado Pago payment.status ∈ {approved, pending,
        // authorized, in_process, in_mediation, rejected, cancelled,
        // refunded, charged_back}. Map to our normalized vocab.
        $mpStatus = (string) ($payment['status'] ?? 'pending');

        $status = match ($mpStatus) {
            'approved'                          => 'paid',
            'rejected', 'cancelled', 'refunded',
            'charged_back'                      => 'failed',
            default                             => 'pending',
        };

        return [
            'status'       => $status,
            'payment_id'   => isset($payment['id']) ? (string) $payment['id'] : null,
            'amount_minor' => isset($payment['transaction_amount'])
                ? $this->wholeToMinor((string) $payment['transaction_amount'], (string) ($payment['currency_id'] ?? 'USD'))
                : null,
        ];
    }

    public function handleWebhook(Request $request, PaymentMethod $method): array
    {
        $creds         = $this->credentials($method);
        $signingSecret = (string) ($creds['webhook_secret'] ?? '');
        $signature     = (string) $request->header('x-signature', '');
        $requestId     = (string) $request->header('x-request-id', '');
        $rawBody       = (string) $request->getContent();
        $event         = \json_decode($rawBody, true) ?: [];

        // data.id is what gets HMAC'd alongside ts + request-id.
        // Mercado Pago also exposes it as the `data.id` query param,
        // but the body is authoritative for our purposes.
        $dataId = (string) ($event['data']['id'] ?? $request->query('data.id', ''));

        $this->verifier->verify(
            signatureHeader: $signature,
            requestIdHeader: $requestId,
            dataId:          $dataId,
            signingSecret:   $signingSecret,
        );

        // The notification only tells us "payment X updated" — we
        // have to fetch the payment to learn its status. The webhook
        // controller will use the returned envelope to find the
        // PosPaymentSession via external_reference.
        $payment = [];
        if ($dataId !== '' && ($event['type'] ?? '') === 'payment') {
            try {
                $client  = $this->clientFor($method);
                $payment = $client->retrievePayment($dataId);
            } catch (RuntimeException $e) {
                // If the fetch fails we still want to acknowledge
                // the webhook (200) so Mercado Pago stops retrying;
                // surface the type/event so the audit log has it.
            }
        }

        return [
            'event_id'     => (string) ($event['id'] ?? \sha1($rawBody)),
            'event_type'   => (string) ($event['action'] ?? $event['type'] ?? ''),
            // session_id ← external_reference so the webhook
            // controller can correlate to PosPaymentSession.
            'session_id'   => (string) ($payment['external_reference'] ?? '') ?: null,
            'payment_id'   => $dataId ?: null,
            'status'       => (string) ($payment['status'] ?? '') ?: null,
            'amount_minor' => isset($payment['transaction_amount'])
                ? $this->wholeToMinor((string) $payment['transaction_amount'], (string) ($payment['currency_id'] ?? 'USD'))
                : null,
            'raw'          => $event + ['_resolved_payment' => $payment],
        ];
    }

    /* ── Helpers ──────────────────────────────────────────────── */

    /** Build a `MercadoPagoClient` from the method's stored credentials. */
    private function clientFor(?PaymentMethod $method): MercadoPagoClient
    {
        if (!$method) {
            throw new RuntimeException('Mercado Pago gateway: no payment method context.');
        }
        $creds = $this->credentials($method);
        return new MercadoPagoClient(
            accessToken: (string) ($creds['access_token'] ?? ''),
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

    /** Minor units → whole-unit decimal string. Mirrors the
     *  Flutterwave helper of the same name. */
    private function minorToWhole(string $amountMinor, string $currency): string
    {
        $decimals = $this->decimalsFor($currency);
        if ($decimals === 0) {
            return (string) (int) $amountMinor;
        }
        return \bcdiv($amountMinor, \bcpow('10', (string) $decimals, 0), $decimals);
    }

    /** Whole-unit string → minor units string. */
    private function wholeToMinor(string $amountWhole, string $currency): string
    {
        $decimals = $this->decimalsFor($currency);
        return \bcmul($amountWhole, \bcpow('10', (string) $decimals, 0), 0);
    }

    private function decimalsFor(string $currency): int
    {
        // CLP is the notable zero-decimal currency among Mercado Pago's
        // supported set. UYU is zero-decimal-display but two-decimal
        // settlement — we use 2 to be safe.
        $zero  = ['JPY', 'KRW', 'VND', 'CLP', 'IDR'];
        $three = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];
        $upper = \strtoupper($currency);
        if (\in_array($upper, $zero,  true)) return 0;
        if (\in_array($upper, $three, true)) return 3;
        return 2;
    }
}
