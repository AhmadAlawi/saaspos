<?php

namespace App\Actions\Installer;

use App\Services\Licensing\LicenseClient;
use App\Services\Licensing\LicenseFingerprint;

/**
 * License validation per docs/features/installer.md §3 Step 3 + §6.
 *
 * In dev mode (APP_DEBUG=true) any non-empty key is accepted with a stub
 * response — the license server isn't contacted. When APP_DEBUG=false the real
 * HTTP call fires via {@see LicenseClient}.
 *
 * Fires `installer.before_validate_license` / `installer.after_validate_license`
 * so plugins can hook the flow (docs/features/installer.md §15).
 */
class ValidateLicense
{
    public function __construct(private readonly LicenseClient $client) {}

    /**
     * @return array{ok: bool, response?: array<string, mixed>, fingerprint?: string, error?: string, reason?: string}
     */
    public function __invoke(string $key, string $installUrl): array
    {
        $key = trim($key);
        if ($key === '') {
            return ['ok' => false, 'error' => __('installer.license.errors.required')];
        }

        do_action('installer.before_validate_license', $key);

        $fingerprint = $this->fingerprint($installUrl);

        // Dev-bypass: returns the stub "valid" response without hitting
        // the license server. OFF by default — must be explicitly opted
        // into via `POS_LICENSE_DEV_BYPASS=true` in `.env`. Previously
        // this was gated on `APP_DEBUG`, which silently masked the real
        // server's responses during day-to-day development and let
        // invalid purchase codes pass the installer.
        if (config('pos.license.dev_bypass')) {
            $response = $this->stubResponse();
            do_action('installer.after_validate_license', $response);

            return ['ok' => true, 'response' => $response, 'fingerprint' => $fingerprint];
        }
        
        $result = $this->client->check([
            'license_key' => $key,
            'install_url' => $installUrl,
            'ip'          => request()->ip(),
            'fingerprint' => $fingerprint,
            'version'     => (string) config('pos.version', '1.0.0'),
        ]);

        // Transport failure (unreachable / http_error / bad_response). There's
        // no body to inspect, so surface the client's structured error.
        if (! $result['ok']) {
            return [
                'ok'     => false,
                'error'  => $result['error'] ?? __('installer.license.errors.invalid'),
                'reason' => $result['reason'] ?? null,
            ];
        }

        $body = $result['body'] ?? [];
        // SmtLicenseServer's contract: { status: 'valid'|'invalid'|
        // 'suspended'|'past_due', plan: {...}, seats: {...}, features:
        // {...}, ... }. No item_id / purchase_code — this instance's
        // license_key alone is enough to identify which product/customer
        // it belongs to server-side.
        $valid = ($body['status'] ?? null) === 'valid';

        if (! $valid) {
            return [
                'ok'     => false,
                'error'  => $body['message'] ?? __('installer.license.errors.invalid'),
                'reason' => $body['reason'] ?? $body['status'] ?? 'invalid',
            ];
        }

        do_action('installer.after_validate_license', $body);

        return ['ok' => true, 'response' => $body, 'fingerprint' => $fingerprint];
    }

    /**
     * Per-install fingerprint (docs §6.4). Delegates to the shared helper so
     * the online + offline activation paths can't drift.
     */
    private function fingerprint(string $installUrl): string
    {
        return LicenseFingerprint::make($installUrl);
    }

    /**
     * @return array<string, mixed>
     */
    private function stubResponse(): array
    {
        return [
            'status'              => 'valid',
            'product'             => 'pos',
            'buyer_name'          => 'Dev Mode',
            'buyer_email'         => 'dev@local',
            'license_type'        => 'regular',
            'expires_at'          => null,
            'support_until'       => now()->addYear()->toIso8601String(),
            'max_installs'        => 1,
            'this_install_number' => 1,
            'note'                => 'Stub response while APP_DEBUG=true. Real validation runs in production.',
        ];
    }
}
