<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Like Laravel's built-in `encrypted` string cast, but it NEVER throws on
 * read. If the stored ciphertext can't be decrypted with the current
 * APP_KEY — because the key was rotated, or the row was copied between
 * environments with different keys — the value reads back as null instead
 * of raising Illuminate\Contracts\Encryption\DecryptException.
 *
 * Why this matters: a single un-decryptable secret (e.g. the SMTP
 * password read by ApplyCompanySettings on every request) would otherwise
 * 500 the entire site. Degrading to null lets the app keep running and
 * lets the operator simply re-save the credential in Settings.
 *
 * Storage format is identical to the `encrypted` cast (Crypt
 * encrypt/decrypt String), so swapping a column from 'encrypted' to this
 * cast is a drop-in change — existing values keep working.
 */
class SafeEncrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            // Wrong key / corrupt payload — treat as "not set" rather than
            // letting the exception bubble up and break the request.
            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString((string) $value);
    }
}
