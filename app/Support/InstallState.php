<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Persistent state for the installer wizard. Lives in storage/app/.installer-state.json.
 *
 * The lock file at storage/app/.install-locked is a SEPARATE marker that signals the
 * installer has run to completion (see docs/features/installer.md §7).
 */
class InstallState
{
    private const STATE_FILE = '.installer-state.json';
    private const LOCK_FILE  = '.install-locked';

    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'language'           => 'en',
        'license_validated'  => false,
        'license_key_hash'   => null,
        'db_configured'      => false,
        'migrations_run'     => false,
        'admin_created'      => false,
        'demo_seeded'        => false,
    ];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (! Storage::disk('local')->exists(self::STATE_FILE)) {
            return self::DEFAULTS;
        }

        $raw = Storage::disk('local')->get(self::STATE_FILE);
        $decoded = json_decode($raw, true) ?? [];

        return array_merge(self::DEFAULTS, $decoded);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $state = self::all();
        $state[$key] = $value;
        Storage::disk('local')->put(self::STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));
    }

    /** @param array<string, mixed> $values */
    public static function setMany(array $values): void
    {
        $state = array_merge(self::all(), $values);
        Storage::disk('local')->put(self::STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));
    }

    public static function clear(): void
    {
        Storage::disk('local')->delete(self::STATE_FILE);
    }

    public static function isLocked(): bool
    {
        return Storage::disk('local')->exists(self::LOCK_FILE);
    }

    public static function lock(string $version, string $installerVersion): void
    {
        Storage::disk('local')->put(self::LOCK_FILE, json_encode([
            'version'           => $version,
            'installer_version' => $installerVersion,
            'installed_at'      => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Determine which step the wizard should resume at, based on completed milestones.
     */
    public static function resumeRoute(): string
    {
        $state = self::all();

        return match (true) {
            ! $state['license_validated'] => 'install.license',
            ! $state['db_configured']     => 'install.database',
            ! $state['migrations_run']    => 'install.database.migrate',
            ! $state['admin_created']     => 'install.admin',
            ! $state['demo_seeded']       => 'install.demo',
            default                       => 'install.welcome',
        };
    }
}
