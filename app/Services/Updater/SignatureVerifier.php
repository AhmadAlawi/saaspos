<?php

namespace App\Services\Updater;

use App\Exceptions\InvalidUpdateSignature;

/**
 * Verifies a downloaded update zip before it's allowed anywhere near the live
 * install (docs/features/updater-hooks.md §4). Two independent guarantees:
 *
 *   1. SHA-256 — the download is bit-perfect (catches truncation / corruption).
 *   2. Ed25519 detached signature — the file genuinely came from us, not a
 *      compromised feed or a man-in-the-middle.
 *
 * Signature policy: with no public key configured, signature checks are
 * skipped ONLY outside production (so local/dev forks can test the flow);
 * production refuses to install an unverifiable package.
 */
class SignatureVerifier
{
    /** Abort unless the file's SHA-256 matches the feed's. */
    public function verifyChecksum(string $filePath, string $expectedSha256): void
    {
        if ($expectedSha256 === '') {
            return;
        }

        $actual = hash_file('sha256', $filePath);
        if ($actual === false || ! hash_equals(strtolower($expectedSha256), strtolower($actual))) {
            throw new InvalidUpdateSignature(__('updates.errors.checksum_mismatch'));
        }
    }

    /** Abort unless the detached Ed25519 signature validates against our key. */
    public function verifySignature(string $filePath, ?string $signatureBase64): void
    {
        $publicKeyBase64 = (string) config('pos.updater.public_key', '');

        if ($publicKeyBase64 === '') {
            $this->failIfProduction('no_public_key');
            return; // non-production: signature verification disabled
        }

        // libsodium ships with PHP by default but a host can disable it. We
        // can't verify without it, so treat it like a missing key: refuse in
        // production, skip elsewhere.
        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            $this->failIfProduction('sodium_unavailable');
            return;
        }

        if (! $signatureBase64) {
            throw new InvalidUpdateSignature(__('updates.errors.signature_missing'));
        }

        $bytes     = file_get_contents($filePath);
        $signature = base64_decode($signatureBase64, true);
        $publicKey = base64_decode($publicKeyBase64, true);

        if ($bytes === false || $signature === false || $publicKey === false) {
            throw new InvalidUpdateSignature(__('updates.errors.signature_invalid'));
        }

        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new InvalidUpdateSignature(__('updates.errors.public_key_invalid'));
        }

        if (! sodium_crypto_sign_verify_detached($signature, $bytes, $publicKey)) {
            throw new InvalidUpdateSignature(__('updates.errors.signature_invalid'));
        }
    }

    /** Run both checks; throws {@see InvalidUpdateSignature} on the first failure. */
    public function verify(string $zipPath, string $expectedSha256, ?string $signatureBase64): void
    {
        $this->verifyChecksum($zipPath, $expectedSha256);
        $this->verifySignature($zipPath, $signatureBase64);
    }

    /**
     * In production, an unverifiable package is fatal. Outside production we
     * let it through so a fork / local install can exercise the flow.
     */
    private function failIfProduction(string $errorKey): void
    {
        if (app()->environment('production')) {
            throw new InvalidUpdateSignature(__("updates.errors.{$errorKey}"));
        }
    }
}
