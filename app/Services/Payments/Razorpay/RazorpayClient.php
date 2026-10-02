<?php

namespace App\Services\Payments\Razorpay;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Razorpay REST API wrapper — raw HTTP, single point of contact.
 *
 * This file is the ONLY place that knows about Razorpay's URLs,
 * headers, and request shape. Same role + shape as StripeClient — the
 * per-provider folder layout exists so a single upstream change is a
 * single-file diff we can ship to customers.
 *
 * Why not the official SDK: a self-hosted POS sold on CodeCanyon
 * can't assume the customer can run `composer install` against their
 * production server. Raw HTTP updates via shipped code.
 *
 * Razorpay API ref: https://razorpay.com/docs/api/
 *
 * Endpoints used:
 *   POST  /v1/payment_links               — create a hosted payment link
 *   GET   /v1/payment_links/:id           — retrieve link status
 *   POST  /v1/payments/:payment_id/refund — refund a captured payment
 *
 * Authentication is HTTP Basic with `key_id` as username and
 * `key_secret` as password.
 */
class RazorpayClient
{
    /** Razorpay REST root. There is no header-based versioning — the
     *  path itself (`/v1/`) carries the version. */
    private const BASE_URL = 'https://api.razorpay.com';

    /** Default HTTP timeout in seconds — Razorpay responses are usually < 1s. */
    private const TIMEOUT = 15;

    public function __construct(
        private readonly string $keyId,
        private readonly string $keySecret,
    ) {
        if ($keyId === '' || $keySecret === '') {
            throw new RuntimeException('Razorpay keys are empty — configure them under Settings → Payment gateways → Razorpay.');
        }
    }

    /**
     * Create a Payment Link. Returns the short URL the customer pays
     * at + the link id we'll later poll.
     *
     * `amountMinor` is in the currency's smallest unit (paise for INR,
     * cents for USD — Razorpay accepts the integer directly).
     *
     * `notes` is opaque key/value pairs Razorpay echoes back on
     * webhooks — we stash the cashier-side `local_uuid` here for
     * correlation, mirroring the Stripe metadata pattern.
     *
     * Razorpay API: https://razorpay.com/docs/api/payments/payment-links/standard/create/
     *
     * @param  array<string, string>  $notes
     */
    public function createPaymentLink(
        int $amountMinor,
        string $currency,
        string $description,
        string $callbackUrl,
        array $notes = [],
    ): array {
        $body = [
            'amount'          => $amountMinor,
            'currency'        => strtoupper($currency),
            'accept_partial'  => false,
            'description'     => $description,
            'callback_url'    => $callbackUrl,
            'callback_method' => 'get',
            // We never want Razorpay to email/SMS the customer — the
            // cashier delivers the link via QR / share button.
            'notify'          => ['sms' => false, 'email' => false],
            'reminder_enable' => false,
        ];
        if (!empty($notes)) {
            $body['notes'] = array_map('strval', $notes);
        }

        return $this->parse($this->post('/v1/payment_links', $body));
    }

    /**
     * Retrieve a Payment Link by id — used by the cashier polling
     * endpoint to detect when the customer has paid.
     *
     * Razorpay API: https://razorpay.com/docs/api/payments/payment-links/standard/fetch-with-id/
     */
    public function retrievePaymentLink(string $linkId): array
    {
        return $this->parse($this->get("/v1/payment_links/{$linkId}"));
    }

    /**
     * Refund a captured Payment. `amountMinor` is optional — omit for
     * a full refund.
     *
     * Razorpay API: https://razorpay.com/docs/api/refunds/create-instant-refunds/
     */
    public function createRefund(string $paymentId, ?int $amountMinor = null): array
    {
        $body = [];
        if ($amountMinor !== null) {
            $body['amount'] = $amountMinor;
        }
        return $this->parse($this->post("/v1/payments/{$paymentId}/refund", $body));
    }

    /* ── HTTP plumbing ────────────────────────────────────────── */

    private function get(string $path): Response
    {
        return Http::withBasicAuth($this->keyId, $this->keySecret)
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->get(self::BASE_URL . $path);
    }

    /** Razorpay expects JSON bodies (unlike Stripe's form encoding). */
    private function post(string $path, array $json): Response
    {
        return Http::withBasicAuth($this->keyId, $this->keySecret)
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->asJson()
            ->post(self::BASE_URL . $path, $json);
    }

    /**
     * Decode the response. Razorpay error envelope:
     *   { "error": { "code": "...", "description": "...", "source": "..." } }
     */
    private function parse(Response $res): array
    {
        $body = $res->json() ?? [];
        if ($res->failed()) {
            $msg = $body['error']['description'] ?? "Razorpay HTTP {$res->status()}";
            throw new RuntimeException("Razorpay: {$msg}");
        }
        return $body;
    }
}
