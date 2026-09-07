<?php

namespace App\Services\Payments\Paystack;

use RuntimeException;

/**
 * Paystack webhook signature verifier — implements the algorithm
 * documented at https://paystack.com/docs/payments/webhooks/.
 *
 * The `X-Paystack-Signature` header is a single hex string:
 *   HMAC-SHA512(raw_body, secret_key)
 *
 * Notes on the algorithm vs Stripe/Razorpay:
 *   - Hash is SHA-512 (not SHA-256). The verifier abstraction stays
 *     identical — only the hash name changes.
 *   - Paystack signs with the SECRET KEY itself, not a separate
 *     webhook secret. The same value powers Bearer auth and webhook
 *     verification. There is no per-endpoint webhook secret to rotate.
 *   - No timestamp tolerance — same as Razorpay.
 *
 * Once both SHA-256 (Stripe/Razorpay) and SHA-512 (Paystack/Flutterwave)
 * variants are running, future ports are mostly endpoint-URL swaps.
 */
class PaystackSignatureVerifier
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
            throw new RuntimeException('Paystack secret key is empty — configure it under Settings.');
        }
        if ($signatureHeader === '') {
            throw new RuntimeException('Missing X-Paystack-Signature header.');
        }

        $expected = \hash_hmac('sha512', $rawBody, $signingSecret);

        // hash_equals is the constant-time compare — never replace
        // with `==`, that's a timing-attack vector.
        if (!\hash_equals($expected, $signatureHeader)) {
            throw new RuntimeException('Paystack webhook signature mismatch.');
        }
    }
}
