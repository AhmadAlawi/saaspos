<?php

namespace App\Services\Payments\MercadoPago;

use RuntimeException;

/**
 * Mercado Pago webhook signature verifier — implements the algorithm
 * documented at https://www.mercadopago.com/developers/en/docs/your-integrations/notifications/webhooks#validate-the-origin-of-a-notification.
 *
 * Mercado Pago's webhook auth is the most involved of our four
 * providers. Each notification carries TWO headers:
 *
 *   x-signature:  ts=<unix-ts>,v1=<hex-sha256>
 *   x-request-id: <uuid>
 *
 * The HMAC isn't over the raw body — it's over a constructed
 * "manifest" string:
 *
 *   id:<payload.data.id>;request-id:<x-request-id>;ts:<ts>;
 *
 * Where:
 *   - <payload.data.id> is the payment id from the notification body
 *     (also present as `data.id` in the query string).
 *   - <x-request-id> is the request-id header value.
 *   - <ts> is parsed out of the x-signature header itself.
 *
 * Then HMAC-SHA256(manifest, webhook_secret) is hex-compared
 * constant-time against the v1 value.
 *
 * Same shape as Stripe's t=,v1= header (and same hash algorithm),
 * but the signed payload is a constructed manifest, not the raw body.
 */
class MercadoPagoSignatureVerifier
{
    /** Reject events whose timestamp is older/newer than this many
     *  seconds — same window as Stripe to keep replay protection
     *  consistent across providers. */
    private const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * @throws RuntimeException when the secret is missing, headers
     *         are missing/malformed, timestamp is outside the window,
     *         or the HMAC doesn't match.
     */
    public function verify(
        string $signatureHeader,
        string $requestIdHeader,
        string $dataId,
        string $signingSecret,
        ?int $now = null,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
    ): void {
        $now = $now ?? time();

        if ($signingSecret === '') {
            throw new RuntimeException('Mercado Pago webhook secret is empty — configure it under Settings.');
        }
        if ($signatureHeader === '') {
            throw new RuntimeException('Missing x-signature header.');
        }
        if ($requestIdHeader === '') {
            throw new RuntimeException('Missing x-request-id header.');
        }
        if ($dataId === '') {
            throw new RuntimeException('Mercado Pago webhook missing data.id — cannot verify.');
        }

        [$timestamp, $candidate] = $this->parseHeader($signatureHeader);

        if ($timestamp === null) {
            throw new RuntimeException('x-signature header missing ts.');
        }
        if ($candidate === null) {
            throw new RuntimeException('x-signature header missing v1.');
        }
        if (\abs($now - $timestamp) > $toleranceSeconds) {
            throw new RuntimeException('Mercado Pago webhook timestamp outside tolerance window — possible replay.');
        }

        $manifest = "id:{$dataId};request-id:{$requestIdHeader};ts:{$timestamp};";
        $expected = \hash_hmac('sha256', $manifest, $signingSecret);

        // hash_equals is the constant-time compare — never replace
        // with `==`, that's a timing-attack vector.
        if (!\hash_equals($expected, $candidate)) {
            throw new RuntimeException('Mercado Pago webhook signature mismatch.');
        }
    }

    /**
     * Parse `ts=...,v1=...` (in either order, possibly with spaces).
     *
     * @return array{0: ?int, 1: ?string}
     */
    private function parseHeader(string $header): array
    {
        $ts = null;
        $v1 = null;

        foreach (\explode(',', $header) as $pair) {
            $pair = \trim($pair);
            if (!\str_contains($pair, '=')) continue;
            [$k, $v] = \explode('=', $pair, 2);
            $k = \trim($k);
            $v = \trim($v);
            if ($k === 'ts') {
                $ts = \is_numeric($v) ? (int) $v : null;
            } elseif ($k === 'v1') {
                $v1 = $v;
            }
        }

        return [$ts, $v1];
    }
}
