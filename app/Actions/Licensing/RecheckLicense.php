<?php

namespace App\Actions\Licensing;

use App\Actions\Installer\PersistLicense;
use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use App\Services\Licensing\LicenseClient;
use App\Services\Licensing\LicenseFingerprint;

/**
 * Background license re-check (docs/features/installer.md §6.3; SaaS
 * conversion plan Phase 2).
 *
 * Re-POSTs the stored license key + current usage to the license server
 * roughly every 30 days and refreshes the cached plan/seat/feature
 * entitlements on the company row. The cadence guard lives here (not in the
 * scheduler) so it survives missed cron runs and a manual "Re-check now" can
 * force it.
 *
 * **A failed re-check NEVER locks the app immediately.** We never hold the
 * customer's data hostage to a single network call: an unreachable server
 * only records the error (the admin banner shows it). But unlike the legacy
 * one-time-validate flow, a SaaS subscription needs an actual grace
 * *deadline* — after {@see GRACE_DAYS} of consecutive failed checks,
 * {@see graceExpired()} lets callers soft-restrict new-record creation
 * (never reads/exports/reporting — a customer must always be able to get
 * their data out).
 *
 * A server that actively rejects/suspends the key flips `license_status`
 * accordingly but the app keeps running — the banner/restriction is the
 * only consequence, matching the same "never lock the app" philosophy.
 *
 * Offline-activated installs (no online key) are skipped — there's no server
 * for them to reach.
 */
class RecheckLicense
{
    /** Re-check at most this often unless forced. */
    private const INTERVAL_DAYS = 30;

    /** Consecutive unreachable days after which functionality soft-restricts. */
    private const GRACE_DAYS = 14;

    public function __construct(
        private readonly LicenseClient $client,
        private readonly PersistLicense $persist,
    ) {}

    /**
     * @return array{ok: bool, skipped?: bool, reason?: string, status?: string, error?: string}
     */
    public function __invoke(bool $force = false): array
    {
        // License enforcement disabled (config/pos.php → license.required):
        // nothing to verify, ever.
        if (! config('pos.license.required')) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'disabled'];
        }

        // Panel-side re-verification switched off (config/pos.php →
        // license.recheck). The installer already validated the code once; the
        // running app doesn't re-litigate it, so there's nothing to do here —
        // not even on a forced "Re-check now".
        if (! config('pos.license.recheck')) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'recheck_disabled'];
        }

        $company = Company::current();
        if ($company === null) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'no_company'];
        }

        $key = trim((string) config('pos.license.key', ''));

        // Offline activation (or no key) → nothing to verify online. Leave the
        // status exactly as the offline blob set it.
        if ($key === '' || strtoupper($key) === 'OFFLINE') {
            return ['ok' => true, 'skipped' => true, 'reason' => 'offline', 'status' => $company->license_status];
        }

        // Cadence guard — only the scheduled (non-forced) path respects it.
        if (! $force && $this->checkedRecently($company)) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'fresh', 'status' => $company->license_status];
        }

        $installUrl = (string) (config('app.url') ?: url('/'));

        $result = $this->client->check([
            'license_key' => $key,
            'install_url' => $installUrl,
            'fingerprint' => (string) config('pos.license.fingerprint', '') ?: LicenseFingerprint::make($installUrl),
            'version'     => (string) config('pos.version', '1.0.0'),
            'usage'       => [
                'user_count'  => User::query()->count(),
                'store_count' => Store::query()->count(),
            ],
        ]);

        // Couldn't reach the server → record the error, keep the current status.
        // The app stays fully functional immediately; a grace deadline starts
        // (or continues) counting down, past which callers may soft-restrict.
        if (! $result['ok']) {
            $company->forceFill([
                'license_last_checked_at' => now(),
                'license_last_error'      => $result['error'] ?? __('installer.license.errors.unreachable'),
                'license_grace_until'     => $company->license_grace_until ?? now()->addDays(self::GRACE_DAYS),
            ])->save();
            forget_app_license();
            forget_app_features();

            return ['ok' => false, 'error' => $result['error'] ?? null, 'status' => $company->license_status];
        }

        $body = $result['body'];
        $status = $body['status'] ?? null;

        if (in_array($status, ['valid', 'suspended', 'past_due'], true)) {
            // Refresh buyer/type/support window + plan/seat/feature
            // entitlements, and clear any prior grace deadline — the server
            // is reachable and has spoken, whatever it said.
            ($this->persist)($body);

            return ['ok' => true, 'status' => $status];
        }

        // Server reached us and rejected the key outright. Flag invalid +
        // keep the message — but STILL never lock the app.
        $company->forceFill([
            'license_status'          => 'invalid',
            'license_last_checked_at' => now(),
            'license_last_error'      => $body['message'] ?? __('installer.license.errors.invalid'),
            'license_grace_until'     => null,
        ])->save();
        forget_app_license();
        forget_app_features();

        return ['ok' => true, 'status' => 'invalid'];
    }

    private function checkedRecently(Company $company): bool
    {
        $last = $company->license_last_checked_at;

        return $last !== null && $last->diffInDays(now()) < self::INTERVAL_DAYS;
    }

    /**
     * True once the grace deadline (set on the first unreachable check) has
     * passed. Callers use this to soft-restrict *new-record creation only* —
     * reads, exports, and reporting must never be gated on this.
     */
    public static function graceExpired(Company $company): bool
    {
        $until = $company->license_grace_until;

        return $until !== null && $until->isPast();
    }
}
