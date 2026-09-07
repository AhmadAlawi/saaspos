<?php

namespace App\Services\Payments\Razorpay;

use RuntimeException;

/**
 * Razorpay webhook signature verifier — implements the algorithm
 * documented at https://razorpay.com/docs/webhooks/validate-test/.
 *
 * The `X-Razorpay-Signature` header is a single hex string:
 *   HMAC-SHA256(raw_body, webhook_secret)
 *
 * Simpler than Stripe — no timestamp tolerance, no comma-separated
 * versioned signatures. We still constant-time-compare the hex strings.
 *
 * Same shape as the Stripe verifier (HMAC-SHA256), different framing.
 * Once we have both running, ports to Paystack / Flutterwave / Mercado
 * Pago are mostly hash + header swaps.
 */
class RazorpaySignatureVerifier
{
    /**
     * @throws RuntimeException when the signature is malformed or
     *         doesn't match the body.
     */
    public function verify(
        string $rawBody,
        string $signatureHeader,
        string $signingSecret,
    ): void {
        if ($signingSecret === '') {
            throw new RuntimeException('Razorpay webhook signing secret is empty — configure it under Settings.');
        }
        if ($signatureHeader === '') {
            throw new RuntimeException('Missing X-Razorpay-Signature header.');
        }

        $expected = \hash_hmac('sha256', $rawBody, $signingSecret);

        // hash_equals is the constant-time compare — never replace
        // with `==`, that's a timing-attack vector.
        if (!\hash_equals($expected, $signatureHeader)) {
            throw new RuntimeException('Razorpay webhook signature mismatch.');
        }
    }
}
