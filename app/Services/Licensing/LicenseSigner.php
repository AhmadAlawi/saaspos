<?php

namespace App\Services\Licensing;

/**
 * HMAC-SHA256 request signing for the SaaS license server (see the phase-2
 * contract in the SaaS conversion plan). The shared secret is minted once by
 * the license server at provisioning (`POST /api/v1/instances/register`)
 * and never re-transmitted — signing proves the request came from an
 * instance that already holds it, without putting anything secret in the
 * request itself.
 *
 * Signing over `license_key|timestamp|body` (not just the body) binds the
 * signature to a specific instance and time window, so a captured request
 * can't be replayed against a different license or after the timestamp
 * window in {@see LicenseClient} has elapsed.
 */
class LicenseSigner
{
    public function sign(string $licenseKey, string $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', "{$licenseKey}|{$timestamp}|{$body}", $secret);
    }

    public function verify(string $licenseKey, string $timestamp, string $body, string $secret, string $signature): bool
    {
        return hash_equals($this->sign($licenseKey, $timestamp, $body, $secret), $signature);
    }
}
