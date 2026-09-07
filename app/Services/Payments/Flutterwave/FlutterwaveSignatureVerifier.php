<?php

namespace App\Services\Payments\Flutterwave;

use RuntimeException;

/**
 * Flutterwave webhook signature verifier.
 *
 * Flutterwave's webhook auth is unusual vs Stripe / Razorpay /
 * Paystack: it is NOT HMAC. The merchant sets a free-form "secret
 * hash" string in the Flutterwave Dashboard → Settings → Webhooks,
 * and Flutterwave echoes that exact string back in the `verif-hash`
 * header on every event delivery.
 *
 * So this verifier is a constant-time string compare between the
 * header and the configured `webhook_secret`. No HMAC, no hashing,
 * no timestamp tolerance.
 *
 * Docs: https://developer.flutterwave.com/docs/integration-guides/webhooks
 */
class FlutterwaveSignatureVerifier
{
    /**
     * @throws RuntimeException when the secret is missing, the header
     *         is missing, or the values don't match.
     */
    public function verify(
        string $signatureHeader,
        string $signingSecret,
    ): void {
        if ($signingSecret === '') {
            throw new RuntimeException('Flutterwave webhook secret hash is empty — configure it under Settings.');
        }
        if ($signatureHeader === '') {
            throw new RuntimeException('Missing verif-hash header.');
        }

        // hash_equals is the constant-time compare — even though there
        // is no hashing here, an attacker can still time-attack a
        // naive `===` to recover the secret byte-by-byte.
        if (!\hash_equals($signingSecret, $signatureHeader)) {
            throw new RuntimeException('Flutterwave webhook signature mismatch.');
        }
    }
}
