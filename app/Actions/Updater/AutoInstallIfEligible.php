<?php

namespace App\Actions\Updater;

use App\Models\Company;
use App\Services\Updater\VersionComparator;

/**
 * Runs after the scheduled feed check: if the customer opted into auto-install
 * AND the pending update is a PATCH-level bump (never minor/major — those stay
 * a deliberate, manual click), install it automatically with the usual
 * pre-update backup + rollback.
 *
 * Eligibility is filterable via `updater.eligible_for_auto_install` so a plugin
 * can veto specific versions.
 */
class AutoInstallIfEligible
{
    public function __construct(
        private readonly VersionComparator $comparator,
        private readonly InstallUpdate $install,
    ) {
    }

    /** @return bool whether an auto-install was performed */
    public function __invoke(): bool
    {
        $company = Company::current();
        if (! $company || ! $company->update_auto_install) {
            return false;
        }

        $version = $company->update_available_version;
        $release = $company->update_available_release;
        if (! $version || ! is_array($release)) {
            return false;
        }

        $current  = (string) config('pos.version', '1.0.0');
        $eligible = $this->comparator->diffType($current, (string) $version) === 'patch';
        $eligible = (bool) apply_filters('updater.eligible_for_auto_install', $eligible, $version, $release);

        if (! $eligible) {
            return false;
        }

        // System-initiated (no user). InstallUpdate handles backup + rollback.
        ($this->install)($release, null);

        return true;
    }
}
