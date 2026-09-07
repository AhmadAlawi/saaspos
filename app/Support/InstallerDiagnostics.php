<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Collects a small, support-friendly diagnostic snapshot for the installer's
 * error screen (docs/features/installer.md §10.1).
 *
 * Deliberately narrow — never a full phpinfo() (§11). Just enough for a
 * customer to paste into a support ticket: versions, limits, the install URL,
 * the last few log lines, and the license key's last 6 chars (never the whole
 * key).
 */
class InstallerDiagnostics
{
    /** @return array<string, string> */
    public static function collect(?string $step = null): array
    {
        return array_filter([
            'Step'          => $step,
            'App version'   => (string) config('pos.version', 'unknown'),
            'PHP version'   => PHP_VERSION,
            'MySQL version' => self::mysqlVersion(),
            'memory_limit'  => (string) ini_get('memory_limit'),
            'max_execution_time' => (string) ini_get('max_execution_time'),
            'Install URL'   => rtrim((string) url('/'), '/'),
            'License key'   => self::licenseTail(),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** A copy-paste plain-text block: the snapshot followed by recent log lines. */
    public static function toText(?string $step = null): string
    {
        $lines = [];
        foreach (self::collect($step) as $key => $value) {
            $lines[] = "{$key}: {$value}";
        }

        $log = self::recentLog();
        if ($log !== '') {
            $lines[] = '';
            $lines[] = '--- Last log lines ---';
            $lines[] = $log;
        }

        return implode("\n", $lines);
    }

    private static function mysqlVersion(): ?string
    {
        try {
            $row = DB::selectOne('select version() as v');

            return $row->v ?? null;
        } catch (\Throwable) {
            // DB not configured yet (early steps) or unreachable — fine.
            return null;
        }
    }

    /** Last 6 chars of the license key, masked. Never the whole key (§11). */
    private static function licenseTail(): ?string
    {
        $key = (string) env('LICENSE_KEY', '');
        if ($key === '') {
            return null;
        }

        return '…'.substr($key, -6);
    }

    /** The tail of storage/logs/laravel.log — last ~50 lines, capped read. */
    private static function recentLog(int $lines = 50): string
    {
        $path = storage_path('logs/laravel.log');
        if (! is_file($path)) {
            return '';
        }

        try {
            $size = filesize($path) ?: 0;
            $fp = fopen($path, 'r');
            if ($fp === false) {
                return '';
            }
            // Read at most the final 64KB so a huge log can't blow memory.
            fseek($fp, max(0, $size - 65536));
            $tail = (string) fread($fp, 65536);
            fclose($fp);

            $rows = array_slice(array_filter(explode("\n", $tail)), -$lines);

            return trim(implode("\n", $rows));
        } catch (\Throwable) {
            return '';
        }
    }
}
