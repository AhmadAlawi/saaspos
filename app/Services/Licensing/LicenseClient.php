<?php

namespace App\Services\Licensing;

use Illuminate\Support\Facades\Http;

/**
 * Talks to the SaaS license server — the single endpoint we control
 * (SaaS conversion plan, Phase 0/2). This is the only place that knows the
 * server's URL and request/response shape. Used both at install time (Step 3)
 * and by the background re-check.
 *
 * The license check must never be allowed to hard-block: an unreachable server
 * resolves to a structured `{ok: false, reason: 'unreachable'}` so the caller
 * can offer the offline-activation path (install) or simply keep running and
 * surface a banner (re-check). The URL passes through the `license.check_url`
 * filter so a fork can point it elsewhere.
 *
 * Signed POST, not GET-with-querystring: the request body carries the
 * license key and (post-SaaS) plan/seat/feature usage data, none of which
 * belongs in a URL that lands in access logs and proxy logs. Every request
 * is HMAC-signed with the per-instance secret issued at provisioning
 * ({@see LicenseSigner}) plus a timestamp, so a captured request can't be
 * replayed later and a leaked license key alone can't forge a request.
 */
class LicenseClient
{
    /** Signed requests older than this are rejected server-side — keep the window tight. */
    private const SIGNATURE_WINDOW_SECONDS = 300;

    public function __construct(private readonly LicenseSigner $signer) {}

    /**
     * POST the validator endpoint with the license key, domain, and usage
     * bound to this install:
     *
     *   POST <check_url>
     *   { license_key, domain_url, fingerprint, version, usage: {...} }
     *
     * The server replies with JSON:
     *   - On valid:   { status: 'valid', plan: {...}, seats: {...}, features: {...}, ... }
     *   - On invalid: { status: 'invalid'|'suspended'|'past_due', message: '…' }
     *
     * Returns a structured result the caller can branch on without knowing
     * the transport details:
     *   - ok=true               → server responded; `body` holds the JSON.
     *   - ok=false, reason=...  → 'unreachable' | 'http_error' | 'bad_response' | 'not_configured'.
     *
     * @param  array<string,mixed>  $payload  license_key, install_url|domain_url, fingerprint, version, usage
     * @return array{ok: bool, body?: array<string,mixed>, status?: int, reason?: string, error?: string}
     */
    public function check(array $payload): array
    {
        $url = (string) apply_filters('license.check_url', (string) config('pos.license.check_url'));
        if ($url === '') {
            return ['ok' => false, 'reason' => 'unreachable', 'error' => __('installer.license.errors.no_server')];
        }

        $licenseKey = (string) ($payload['license_key'] ?? $payload['purchase_code'] ?? '');
        $secret     = (string) config('pos.license.hmac_secret', '');
        if ($licenseKey === '' || $secret === '') {
            return ['ok' => false, 'reason' => 'not_configured', 'error' => __('installer.license.errors.no_server')];
        }

        $body = array_filter([
            'license_key' => $licenseKey,
            'domain_url'  => $payload['install_url'] ?? $payload['domain_url'] ?? null,
            'fingerprint' => $payload['fingerprint'] ?? null,
            'version'     => $payload['version'] ?? null,
            'item_id'     => $payload['item_id'] ?? (string) config('pos.license.item_id', ''),
            'usage'       => $payload['usage'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $jsonBody  = json_encode($body, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->getTimestamp();
        $signature = $this->signer->sign($licenseKey, $timestamp, $jsonBody, $secret);

        try {
            $response = Http::timeout((int) config('pos.license.timeout', 15))
                ->acceptJson()
                ->withHeaders([
                    'X-License-Key'          => $licenseKey,
                    'X-Instance-Fingerprint' => (string) ($payload['fingerprint'] ?? ''),
                    'X-Timestamp'            => $timestamp,
                    'X-Signature'            => $signature,
                ])
                ->withBody($jsonBody, 'application/json')
                ->post($url);
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'reason' => 'unreachable', 'error' => __('installer.license.errors.unreachable')];
        }

        if ($response->failed()) {
            return [
                'ok'     => false,
                'reason' => 'http_error',
                'status' => $response->status(),
                'error'  => __('installer.license.errors.http', ['status' => $response->status()]),
            ];
        }

        $responseBody = $response->json();
        if (! is_array($responseBody)) {
            return ['ok' => false, 'reason' => 'bad_response', 'error' => __('installer.license.errors.bad_response')];
        }

        return ['ok' => true, 'body' => $responseBody, 'status' => $response->status()];
    }
}
