<?php

namespace App\Services\Payments\Stripe;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe REST API wrapper — raw HTTP, single point of contact.
 *
 * This file is the ONLY place that knows about Stripe's URLs, headers,
 * and request shape. If Stripe versions an endpoint or renames a
 * field, this is the only file that needs editing — drop in the
 * change, ship the update to customers, done.
 *
 * Why not an SDK: a self-hosted POS install can't assume
 * customers run `composer install` against a production server. The
 * SDK would update via composer; raw HTTP updates via shipped code.
 * Plus the surface we need is small — three endpoints.
 *
 * Stripe API ref: https://docs.stripe.com/api
 *
 * Endpoints used:
 *   POST  /v1/checkout/sessions     — create a hosted payment session
 *   GET   /v1/checkout/sessions/:id — retrieve session status
 *   POST  /v1/payment_intents       — wallet-button flow (Payment Request Button)
 *   GET   /v1/payment_intents/:id   — retrieve PaymentIntent status
 *   POST  /v1/refunds               — refund a payment intent
 */
class StripeClient
{
    /** Stripe REST root. Versioned via `Stripe-Version` header below. */
    private const BASE_URL = 'https://api.stripe.com';

    /**
     * Pinned API version. Bump explicitly when adopting a new Stripe
     * version — the upgrade notes at
     * https://docs.stripe.com/upgrades become a one-line bump here.
     */
    private const API_VERSION = '2024-12-18.acacia';

    /** Default HTTP timeout in seconds — Stripe responses are usually < 1s. */
    private const TIMEOUT = 15;

    public function __construct(
        private readonly string $secretKey,
    ) {
        if ($secretKey === '') {
            throw new RuntimeException('Stripe secret key is empty — configure it under Settings → Payment methods → Stripe.');
        }
    }

    /**
     * Create a Checkout Session. Returns the URL the customer pays at
     * + the session id we'll later poll.
     *
     * `amountMinor` is in the currency's smallest unit (cents for USD,
     * paise for INR — Stripe accepts the integer directly).
     *
     * `metadata` is opaque key/value pairs Stripe echoes back on
     * webhook events — we stash the cashier-side `local_uuid` here
     * for correlation.
     *
     * Stripe API: https://docs.stripe.com/api/checkout/sessions/create
     *
     * @param  array<string, string>  $metadata
     */
    public function createCheckoutSession(
        int $amountMinor,
        string $currency,
        string $productName,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
    ): array {
        $body = [
            'mode'                 => 'payment',
            'success_url'          => $successUrl,
            'cancel_url'           => $cancelUrl,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency'     => strtolower($currency),
                    'unit_amount'  => $amountMinor,
                    'product_data' => ['name' => $productName],
                ],
            ]],
        ];

        // Stripe accepts nested metadata as `metadata[local_uuid]=...`
        // form fields; the HTTP client form-encodes them correctly.
        foreach ($metadata as $k => $v) {
            $body["metadata[{$k}]"] = (string) $v;
        }

        return $this->parse($this->post('/v1/checkout/sessions', $body));
    }

    /**
     * Retrieve a Checkout Session by id — used by the cashier
     * polling endpoint to detect when the customer has paid.
     *
     * Stripe API: https://docs.stripe.com/api/checkout/sessions/retrieve
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        return $this->parse($this->get("/v1/checkout/sessions/{$sessionId}"));
    }

    /**
     * Create a PaymentIntent for the Payment Request Button flow (Apple
     * Pay / Google Pay rendered directly on our own minimal pay page,
     * rather than redirecting to Stripe's hosted Checkout). Returns the
     * `client_secret` Stripe.js needs to confirm the payment browser-side.
     *
     * `automatic_payment_methods[enabled]=true` is what makes Apple Pay /
     * Google Pay available — they're not separate payment_method_types,
     * they surface automatically under `card` when the customer's
     * device/browser supports them.
     *
     * Stripe API: https://docs.stripe.com/api/payment_intents/create
     *
     * @param  array<string, string>  $metadata
     */
    public function createPaymentIntent(int $amountMinor, string $currency, array $metadata = []): array
    {
        $body = [
            'amount'   => $amountMinor,
            'currency' => strtolower($currency),
            'automatic_payment_methods[enabled]' => 'true',
        ];

        foreach ($metadata as $k => $v) {
            $body["metadata[{$k}]"] = (string) $v;
        }

        return $this->parse($this->post('/v1/payment_intents', $body));
    }

    /**
     * Retrieve a PaymentIntent by id — used by the wallet-flow status
     * poll (StripeGateway::pollStatus()'s `pi_` branch).
     *
     * Stripe API: https://docs.stripe.com/api/payment_intents/retrieve
     */
    public function retrievePaymentIntent(string $id): array
    {
        return $this->parse($this->get("/v1/payment_intents/{$id}"));
    }

    /**
     * Refund a Payment Intent (the underlying charge a Checkout
     * Session creates). `amountMinor` is optional — if omitted, the
     * entire remaining amount is refunded.
     *
     * Stripe API: https://docs.stripe.com/api/refunds/create
     */
    public function createRefund(string $paymentIntentId, ?int $amountMinor = null): array
    {
        $body = ['payment_intent' => $paymentIntentId];
        if ($amountMinor !== null) {
            $body['amount'] = $amountMinor;
        }
        return $this->parse($this->post('/v1/refunds', $body));
    }

    /* ── HTTP plumbing ────────────────────────────────────────── */

    private function get(string $path): Response
    {
        return Http::withBasicAuth($this->secretKey, '')
            ->withHeaders(['Stripe-Version' => self::API_VERSION])
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->get(self::BASE_URL . $path);
    }

    /** Stripe expects form-encoded bodies, NOT JSON. */
    private function post(string $path, array $form): Response
    {
        return Http::withBasicAuth($this->secretKey, '')
            ->withHeaders(['Stripe-Version' => self::API_VERSION])
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->asForm()
            ->post(self::BASE_URL . $path, $form);
    }

    /**
     * Decode the response. Stripe error envelope:
     *   { "error": { "type": "...", "message": "...", "code": "..." } }
     * Surface the message so the cashier sees actionable text.
     */
    private function parse(Response $res): array
    {
        $body = $res->json() ?? [];
        if ($res->failed()) {
            $msg = $body['error']['message'] ?? "Stripe HTTP {$res->status()}";
            throw new RuntimeException("Stripe: {$msg}");
        }
        return $body;
    }
}
