<?php

namespace App\Services\Payments\MercadoPago;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Mercado Pago REST API wrapper — raw HTTP, single point of contact.
 *
 * This file is the ONLY place that knows about Mercado Pago's URLs,
 * headers, and request shape. Same role + shape as the Stripe /
 * Razorpay / Paystack / Flutterwave clients.
 *
 * Mercado Pago API ref: https://www.mercadopago.com/developers/en/reference
 *
 * Endpoints used:
 *   POST /checkout/preferences             — create a hosted checkout preference
 *   GET  /v1/payments/search               — find a payment by external_reference
 *   GET  /v1/payments/{id}                  — fetch a single payment by id
 *   POST /v1/payments/{id}/refunds          — refund a captured payment
 *
 * Authentication is HTTP Bearer with the merchant's `access_token`.
 * Mercado Pago calls the secret credential "access_token" rather
 * than "secret_key" — same role, different name.
 *
 * Notable: like Flutterwave, Mercado Pago amounts go on the wire as
 * WHOLE units (e.g. 100.00) in `items[].unit_price`. The gateway
 * converts from our `amountMinor` contract before calling this client.
 */
class MercadoPagoClient
{
    /** Mercado Pago REST root. */
    private const BASE_URL = 'https://api.mercadopago.com';

    /** Default HTTP timeout in seconds. */
    private const TIMEOUT = 15;

    public function __construct(
        private readonly string $accessToken,
    ) {
        if ($accessToken === '') {
            throw new RuntimeException('Mercado Pago access token is empty — configure it under Settings → Payment gateways → Mercado Pago.');
        }
    }

    /**
     * Create a Checkout Preference. Returns the hosted-checkout URLs
     * + the preference id we'll later use for status lookups.
     *
     * `unitPrice` is the WHOLE-unit decimal-string (e.g. "100.00").
     * `externalReference` is our handle (we use the session uuid) so
     * the search-by-external-reference call later finds the payment.
     *
     * Mercado Pago API: https://www.mercadopago.com/developers/en/reference/preferences/_checkout_preferences/post
     *
     * @param  array<string, string|null>  $backUrls  ['success'=>?, 'pending'=>?, 'failure'=>?]
     * @param  array<string, mixed>  $metadata
     */
    public function createPreference(
        string $title,
        string $unitPrice,
        string $currencyId,
        string $externalReference,
        array $backUrls,
        ?string $notificationUrl,
        array $metadata = [],
    ): array {
        $body = [
            'items' => [[
                'title'       => $title,
                'quantity'    => 1,
                'unit_price'  => (float) $unitPrice,
                'currency_id' => strtoupper($currencyId),
            ]],
            'external_reference' => $externalReference,
            'back_urls'          => array_filter($backUrls, fn ($v) => $v !== null && $v !== ''),
            // auto_return only fires for approved payments — pending
            // / failed leave the customer on Mercado Pago's screen.
            'auto_return'        => 'approved',
        ];
        if ($notificationUrl) {
            $body['notification_url'] = $notificationUrl;
        }
        if (!empty($metadata)) {
            $body['metadata'] = $metadata;
        }

        return $this->parse($this->post('/checkout/preferences', $body));
    }

    /**
     * Look up a payment by our external_reference — used for cashier
     * polling. Returns the first matching payment hash or null.
     *
     * Mercado Pago API: https://www.mercadopago.com/developers/en/reference/payments/_payments_search/get
     */
    public function findPaymentByExternalReference(string $externalReference): ?array
    {
        $response = $this->parse($this->get('/v1/payments/search', [
            'external_reference' => $externalReference,
            // Most-recent first so we read the live state.
            'sort'               => 'date_created',
            'criteria'           => 'desc',
            'limit'              => 1,
        ]));
        $results = $response['results'] ?? [];
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Fetch a single payment by id — used by the webhook handler
     * (the notification body contains only the payment id; we have
     * to retrieve the payment to learn its status).
     *
     * Mercado Pago API: https://www.mercadopago.com/developers/en/reference/payments/_payments_id/get
     */
    public function retrievePayment(string $paymentId): array
    {
        return $this->parse($this->get("/v1/payments/{$paymentId}"));
    }

    /**
     * Refund a captured payment. `amount` is optional — omit for full.
     *
     * Mercado Pago API: https://www.mercadopago.com/developers/en/reference/chargebacks/_payments_id_refunds/post
     */
    public function refundPayment(string $paymentId, ?string $amount = null): array
    {
        $body = [];
        if ($amount !== null) {
            $body['amount'] = (float) $amount;
        }
        return $this->parse($this->post("/v1/payments/{$paymentId}/refunds", $body));
    }

    /* ── HTTP plumbing ────────────────────────────────────────── */

    /** @param array<string, scalar> $query */
    private function get(string $path, array $query = []): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->get(self::BASE_URL . $path, $query);
    }

    private function post(string $path, array $json): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->asJson()
            ->post(self::BASE_URL . $path, $json);
    }

    /**
     * Decode the response. Mercado Pago error envelope varies but
     * always carries `message` or `error` we can surface.
     */
    private function parse(Response $res): array
    {
        $body = $res->json() ?? [];
        if ($res->failed()) {
            $msg = $body['message'] ?? $body['error'] ?? "Mercado Pago HTTP {$res->status()}";
            throw new RuntimeException("Mercado Pago: {$msg}");
        }
        return $body;
    }
}
