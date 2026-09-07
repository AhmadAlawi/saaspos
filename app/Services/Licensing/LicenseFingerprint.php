<?php

namespace App\Services\Licensing;

/**
 * Per-install fingerprint — a SOFT signal to distinguish re-installs from
 * license sharing (docs/features/installer.md §6.4). Salted with APP_KEY so two
 * installs at the same path still differ. Shared by the online check and the
 * offline activation path so both write the same LICENSE_FINGERPRINT to .env.
 */
class LicenseFingerprint
{
    public static function make(string $installUrl): string
    {
        return hash('sha256', implode('|', [
            base_path(),
            $installUrl,
            (string) config('app.key', ''),
        ]));
    }
}
