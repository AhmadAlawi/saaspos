<?php

namespace App\Services\Payments\Stripe;

use RuntimeException;

/**
 * Stripe webhook signature verifier — implements the algorithm
 * documented at https://docs.stripe.com/webhooks#verify-manually.
 *
 * The `Stripe-Signature` header is a comma-separated list of fields:
 *   t=<timestamp>,v1=<sha256>,v1=<sha256>,...
 *
 * Verification:
 *   1. Split out `t` and each `v1`.
 *   2. Form the signed payload: `<t>.<raw body>`.
 *   3. HMAC-SHA256(signed_payload, signing_secret).
 *   4. Compare the computed hash to each `v1` using a constant-time
 *      compare. At least one must match.
 *   5. (Optional) Reject when `abs(now - t) > tolerance` to prevent
 *      replay attacks. Default tolerance: 5 minutes.
 *
 * Same algorithm shape as Razorpay (HMAC-SHA256, separate header) and
 * Paystack/Flutterwave (different hash, same family). Once this is
 * mentally proven against Stripe, ports are straightforward.
 */
class StripeSignatureVerifier
{
    /** Reject events whose timestamp is older/newer than this many seconds. */
    private const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * @throws RuntimeException when the signature is malformed,
     *         doesn't match, or falls outside the tolerance window.
     */
    public function verify(
        string $rawBody,
        string $signatureHeader,
        string $signingSecret,
        ?int $now = null,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
    ): void {
        $now = $now ?? time();

        if ($signingSecret === '') {
            throw new RuntimeException('Stripe webhook signing secret is empty — configure it under Settings.');
        }
        if ($signatureHeader === '') {
            throw new RuntimeException('Missing Stripe-Signature header.');
        }

        [$timestamp, $signatures] = $this->parseHeader($signatureHeader);

        if ($timestamp === null) {
            throw new RuntimeException('Stripe-Signature header missing timestamp.');
        }
        if (empty($signatures)) {
            throw new RuntimeException('Stripe-Signature header missing v1 signatures.');
        }

        if (\abs($now - $timestamp) > $toleranceSeconds) {
            throw new RuntimeException('Stripe webhook timestamp outside tolerance window — possible replay.');
        }

        $signedPayload = $timestamp . '.' . $rawBody;
        $expected      = \hash_hmac('sha256', $signedPayload, $signingSecret);

        foreach ($signatures as $candidate) {
            // hash_equals is the constant-time compare — never replace
            // with `==`, that's a timing-attack vector.
            if (\hash_equals($expected, $candidate)) {
                return;
            }
        }

        throw new RuntimeException('Stripe webhook signature mismatch.');
    }

    /**
     * Parse `t=...,v1=...,v1=...` into a timestamp + signature list.
     *
     * @return array{0: ?int, 1: array<int, string>}
     */
    private function parseHeader(string $header): array
    {
        $timestamp = null;
        $sigs      = [];

        foreach (\explode(',', $header) as $pair) {
            $pair = \trim($pair);
            if (!\str_contains($pair, '=')) continue;
            [$k, $v] = \explode('=', $pair, 2);
            if ($k === 't') {
                $timestamp = \is_numeric($v) ? (int) $v : null;
            } elseif ($k === 'v1') {
                $sigs[] = $v;
            }
        }

        return [$timestamp, $sigs];
    }
}
