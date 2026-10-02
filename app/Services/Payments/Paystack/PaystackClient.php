<?php

namespace App\Services\Payments\Paystack;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paystack REST API wrapper — raw HTTP, single point of contact.
 *
 * This file is the ONLY place that knows about Paystack's URLs,
 * headers, and request shape. Same role + shape as StripeClient and
 * RazorpayClient — the per-provider folder layout keeps an upstream
 * change to a single-file diff.
 *
 * Why not the official SDK: a self-hosted POS sold on CodeCanyon
 * can't assume the customer can run `composer install` on their
 * production server. Raw HTTP updates via shipped code.
 *
 * Paystack API ref: https://paystack.com/docs/api/
 *
 * Endpoints used:
 *   POST  /transaction/initialize         — create a hosted checkout
 *   GET   /transaction/verify/:reference  — retrieve transaction status
 *   POST  /refund                         — refund a captured payment
 *
 * Authentication is HTTP Bearer with the merchant's `secret_key`
 * (unlike Razorpay's Basic Auth). Paystack signs webhooks with the
 * SAME secret_key — there is no separate webhook secret.
 */
class PaystackClient
{
    /** Paystack REST root. There is no header-based versioning. */
    private const BASE_URL = 'https://api.paystack.co';

    /** Default HTTP timeout in seconds — Paystack responses are usually < 1s. */
    private const TIMEOUT = 15;

    public function __construct(
        private readonly string $secretKey,
    ) {
        if ($secretKey === '') {
            throw new RuntimeException('Paystack secret key is empty — configure it under Settings → Payment gateways → Paystack.');
        }
    }

    /**
     * Initialize a Transaction. Returns the hosted-checkout URL the
     * customer pays at + the reference we'll later verify.
     *
     * `amountMinor` is in the currency's smallest unit — kobo for NGN,
     * pesewa for GHS, cents for ZAR/USD. Paystack accepts the integer.
     *
     * `metadata` is opaque key/value pairs Paystack echoes back on
     * webhooks and the verify endpoint — we stash the cashier-side
     * `local_uuid` here for correlation, mirroring the Stripe/Razorpay
     * convention.
     *
     * Paystack requires an `email` — we pass a placeholder for walk-in
     * sales; merchants who want real receipts can wire customer email
     * later via the cashier flow.
     *
     * Paystack API: https://paystack.com/docs/api/transaction/#initialize
     *
     * @param  array<string, string>  $metadata
     */
    public function initializeTransaction(
        int $amountMinor,
        string $currency,
        string $email,
        string $reference,
        string $callbackUrl,
        array $metadata = [],
    ): array {
        $body = [
            'amount'       => $amountMinor,
            'currency'     => strtoupper($currency),
            'email'        => $email,
            'reference'    => $reference,
            'callback_url' => $callbackUrl,
        ];
        if (!empty($metadata)) {
            // Paystack accepts metadata as a JSON object; pass-through
            // — keys are surfaced as `custom_fields` in the dashboard.
            $body['metadata'] = array_map('strval', $metadata);
        }

        $data = $this->parse($this->post('/transaction/initialize', $body));
        // Paystack wraps every response in {status, message, data: {...}}
        // — flatten the data envelope so the gateway code sees the
        // same shape as Stripe/Razorpay.
        return $data['data'] ?? [];
    }

    /**
     * Verify a transaction by reference — used by both the cashier
     * polling endpoint and the customer-return flow.
     *
     * Paystack API: https://paystack.com/docs/api/transaction/#verify
     */
    public function verifyTransaction(string $reference): array
    {
        $data = $this->parse($this->get("/transaction/verify/{$reference}"));
        return $data['data'] ?? [];
    }

    /**
     * Refund a captured Transaction. `amountMinor` is optional — omit
     * for a full refund.
     *
     * Paystack API: https://paystack.com/docs/api/refund/#create
     */
    public function createRefund(string $reference, ?int $amountMinor = null): array
    {
        $body = ['transaction' => $reference];
        if ($amountMinor !== null) {
            $body['amount'] = $amountMinor;
        }
        $data = $this->parse($this->post('/refund', $body));
        return $data['data'] ?? [];
    }

    /* ── HTTP plumbing ────────────────────────────────────────── */

    private function get(string $path): Response
    {
        return Http::withToken($this->secretKey)
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->get(self::BASE_URL . $path);
    }

    /** Paystack expects JSON bodies. */
    private function post(string $path, array $json): Response
    {
        return Http::withToken($this->secretKey)
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->asJson()
            ->post(self::BASE_URL . $path, $json);
    }

    /**
     * Decode the response. Paystack error envelope:
     *   { "status": false, "message": "..." }
     */
    private function parse(Response $res): array
    {
        $body = $res->json() ?? [];
        if ($res->failed() || ($body['status'] ?? false) === false) {
            $msg = $body['message'] ?? "Paystack HTTP {$res->status()}";
            throw new RuntimeException("Paystack: {$msg}");
        }
        return $body;
    }
}
