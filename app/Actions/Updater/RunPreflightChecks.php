<?php

namespace App\Actions\Updater;

use Illuminate\Support\Facades\DB;

/**
 * Pre-flight gate for an install (docs/features/updater-hooks.md §3.1 step 1).
 * Pure inspection — changes nothing. Returns one row per check so the install
 * wizard can render a tick/cross list and block the button when a *required*
 * check fails.
 *
 * Plugins can append their own via the `updater.preflight_checks` filter.
 */
class RunPreflightChecks
{
    /**
     * @param  array<string,mixed>  $release
     * @return list<array{key:string,label:string,passed:bool,message:?string,required:bool}>
     */
    public function __invoke(array $release): array
    {
        $checks = [];

        // PHP version.
        $minPhp = (string) ($release['min_php'] ?? '');
        $checks[] = $this->result(
            'php',
            __('updates.preflight.php', ['min' => $minPhp ?: '—']),
            $minPhp === '' || version_compare(PHP_VERSION, $minPhp, '>='),
            __('updates.preflight.php_have', ['have' => PHP_VERSION]),
        );

        // MySQL version — only meaningful on MySQL; skipped elsewhere (tests use SQLite).
        $minMysql = (string) ($release['min_mysql'] ?? '');
        if ($minMysql !== '' && DB::connection()->getDriverName() === 'mysql') {
            $have = $this->mysqlVersion();
            $checks[] = $this->result(
                'mysql',
                __('updates.preflight.mysql', ['min' => $minMysql]),
                $have !== null && version_compare($have, $minMysql, '>='),
                $have ? __('updates.preflight.mysql_have', ['have' => $have]) : null,
            );
        }

        // Disk space — need roughly 3× the zip (zip + extracted + backup buffer).
        $needed = (int) ($release['size_bytes'] ?? 0) * 3;
        $free   = @disk_free_space(base_path());
        if ($free === false) {
            $checks[] = $this->result('disk', __('updates.preflight.disk'), true, __('updates.preflight.disk_unknown'), false);
        } else {
            $checks[] = $this->result(
                'disk',
                __('updates.preflight.disk'),
                $needed === 0 || $free >= $needed,
                __('updates.preflight.disk_have', ['have' => $this->mb($free), 'need' => $this->mb($needed)]),
            );
        }

        // Writable paths the update needs to touch.
        foreach ([
            'storage'        => storage_path(),
            'bootstrap_cache' => base_path('bootstrap/cache'),
            'app_root'       => base_path(),
        ] as $key => $path) {
            $checks[] = $this->result(
                'writable_'.$key,
                __('updates.preflight.writable', ['path' => $this->relative($path)]),
                is_writable($path),
            );
        }

        return apply_filters('updater.preflight_checks', $checks, $release);
    }

    /** True only if every required check passed. */
    public function passed(array $checks): bool
    {
        foreach ($checks as $check) {
            if (($check['required'] ?? true) && ! ($check['passed'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{key:string,label:string,passed:bool,message:?string,required:bool}
     */
    private function result(string $key, string $label, bool $passed, ?string $message = null, bool $required = true): array
    {
        return compact('key', 'label', 'passed', 'message', 'required');
    }

    private function mysqlVersion(): ?string
    {
        try {
            $row = DB::selectOne('select version() as v');
            $raw = $row->v ?? null;

            // "8.0.36-0ubuntu0.22.04.1" → "8.0.36"
            return $raw ? (preg_split('/[^0-9.]/', (string) $raw)[0] ?: null) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function mb(int $bytes): string
    {
        return number_format($bytes / 1024 / 1024, 0).' MB';
    }

    private function relative(string $path): string
    {
        $base = base_path();

        return str_starts_with($path, $base) ? (trim(substr($path, strlen($base)), '/\\') ?: '/') : $path;
    }
}
