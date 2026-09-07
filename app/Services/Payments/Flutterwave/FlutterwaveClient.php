<?php

namespace App\Services\Payments\Flutterwave;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Flutterwave REST API wrapper — raw HTTP, single point of contact.
 *
 * This file is the ONLY place that knows about Flutterwave's URLs,
 * headers, and request shape. Same role + shape as the Stripe/
 * Razorpay/Paystack clients — per-provider folders so an upstream
 * change ships as a single-file diff to customers.
 *
 * Flutterwave API ref: https://developer.flutterwave.com/reference/
 *
 * Endpoints used:
 *   POST  /v3/payments                                   — create a hosted checkout
 *   GET   /v3/transactions/verify_by_reference?tx_ref=…  — verify by our reference
 *   POST  /v3/transactions/{id}/refund                   — refund a captured txn
 *
 * Authentication is HTTP Bearer with the merchant's `secret_key`.
 *
 * Notable: Flutterwave amounts go on the wire as WHOLE units
 * (e.g. "100.00" for ₦100), NOT minor units. The gateway code
 * converts before calling this client.
 */
class FlutterwaveClient
{
    /** Flutterwave REST root. */
    private const BASE_URL = 'https://api.flutterwave.com';

    /** Default HTTP timeout in seconds. */
    private const TIMEOUT = 15;

    public function __construct(
        private readonly string $secretKey,
    ) {
        if ($secretKey === '') {
            throw new RuntimeException('Flutterwave secret key is empty — configure it under Settings → Payment gateways → Flutterwave.');
        }
    }

    /**
     * Create a hosted payment session. Returns the hosted-checkout
     * `link` the customer pays at.
     *
     * `amount` is the WHOLE-unit decimal-string in the given currency
     * (e.g. "100.00" for ₦100). Pre-converted by the gateway from our
     * `amountMinor` contract.
     *
     * `txRef` is our idempotency / lookup id — we reuse the
     * PosPaymentSession uuid so polling / return / webhook share one
     * handle (mirrors how Paystack uses `reference`).
     *
     * `meta` is opaque key/value pairs Flutterwave echoes back on
     * webhooks and verify — we stash the cashier-side `local_uuid`.
     *
     * Flutterwave API: https://developer.flutterwave.com/reference/endpoints/standard
     *
     * @param  array{email: string, name?: string, phonenumber?: string}  $customer
     * @param  array<string, string>  $meta
     */
    public function createCharge(
        string $amount,
        string $currency,
        string $txRef,
        string $redirectUrl,
        string $title,
        array $customer,
        array $meta = [],
    ): array {
        $body = [
            'tx_ref'        => $txRef,
            'amount'        => $amount,
            'currency'      => strtoupper($currency),
            'redirect_url'  => $redirectUrl,
            'customer'      => $customer,
            'customizations'=> ['title' => $title],
        ];
        if (!empty($meta)) {
            $body['meta'] = array_map('strval', $meta);
        }

        $data = $this->parse($this->post('/v3/payments', $body));
        return $data['data'] ?? [];
    }

    /**
     * Verify a transaction by our reference — used by both the
     * cashier polling endpoint and the customer-return flow.
     *
     * Flutterwave API: https://developer.flutterwave.com/reference/endpoints/transactions/#verify-transaction
     */
    public function verifyByReference(string $txRef): array
    {
        $data = $this->parse($this->get('/v3/transactions/verify_by_reference', ['tx_ref' => $txRef]));
        return $data['data'] ?? [];
    }

    /**
     * Refund a captured Transaction. `amount` is optional — omit for
     * a full refund. Note the path takes Flutterwave's numeric `id`
     * (from the verify response), not our `tx_ref`.
     *
     * Flutterwave API: https://developer.flutterwave.com/reference/endpoints/transactions/#refund-a-transaction
     */
    public function createRefund(int $transactionId, ?string $amount = null): array
    {
        $body = [];
        if ($amount !== null) {
            $body['amount'] = $amount;
        }
        $data = $this->parse($this->post("/v3/transactions/{$transactionId}/refund", $body));
        return $data['data'] ?? [];
    }

    /* ── HTTP plumbing ────────────────────────────────────────── */

    /** @param array<string, string> $query */
    private function get(string $path, array $query = []): Response
    {
        return Http::withToken($this->secretKey)
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->get(self::BASE_URL . $path, $query);
    }

    /** Flutterwave expects JSON bodies. */
    private function post(string $path, array $json): Response
    {
        return Http::withToken($this->secretKey)
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->asJson()
            ->post(self::BASE_URL . $path, $json);
    }

    /**
     * Decode the response. Flutterwave wraps every response in
     *   { "status": "success" | "error", "message": "...", "data": {...} }
     */
    private function parse(Response $res): array
    {
        $body = $res->json() ?? [];
        if ($res->failed() || ($body['status'] ?? '') !== 'success') {
            $msg = $body['message'] ?? "Flutterwave HTTP {$res->status()}";
            throw new RuntimeException("Flutterwave: {$msg}");
        }
        return $body;
    }
}
