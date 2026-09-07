<?php

namespace App\Actions\System;

use App\Actions\Installer\CheckRequirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gathers a read-only "is this install healthy?" snapshot for the admin
 * System Health page. Groups a set of checks — application config, database,
 * storage, background jobs, PHP/server requirements, optional integrations —
 * each with a status (`ok` | `warn` | `fail`), the observed value, and a hint
 * on how to fix it when it isn't `ok`.
 *
 * Purely diagnostic: it reads state, never mutates the system.
 *
 * @phpstan-type Row array{label:string, value:string, status:string, hint:?string}
 * @phpstan-type Group array{title:string, rows:list<Row>}
 */
class CheckSystemHealth
{
    public function __construct(private CheckRequirements $requirements) {}

    /**
     * @return array{groups: list<array<string,mixed>>, summary: array{ok:int, warn:int, fail:int, status:string}}
     */
    public function __invoke(): array
    {
        $groups = [
            $this->application(),
            $this->database(),
            $this->storage(),
            $this->jobs(),
            $this->phpServer(),
            $this->integrations(),
        ];

        return [
            'groups'  => $groups,
            'summary' => $this->summarize($groups),
        ];
    }

    /** @return array<string,mixed> */
    private function application(): array
    {
        $env     = (string) config('app.env');
        $debug   = (bool) config('app.debug');
        $isProd  = $env === 'production';
        $hasKey  = (string) config('app.key') !== '';

        return [
            'title' => __('system_health.groups.application'),
            'rows'  => [
                $this->row('system_health.rows.version', (string) config('pos.version', '—'), 'ok'),
                $this->row(
                    'system_health.rows.environment',
                    $env,
                    $isProd ? 'ok' : 'warn',
                    $isProd ? null : 'system_health.hints.environment',
                ),
                $this->row(
                    'system_health.rows.debug',
                    $debug ? 'On' : 'Off',
                    // Debug ON in production leaks stack traces — that's a hard fail.
                    $debug ? ($isProd ? 'fail' : 'warn') : 'ok',
                    $debug ? 'system_health.hints.debug' : null,
                ),
                $this->row(
                    'system_health.rows.app_key',
                    $hasKey ? __('system_health.values.set') : __('system_health.values.missing'),
                    $hasKey ? 'ok' : 'fail',
                    $hasKey ? null : 'system_health.hints.app_key',
                ),
                $this->row('system_health.rows.timezone', (string) config('app.timezone', 'UTC'), 'ok'),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function database(): array
    {
        $rows = [];

        try {
            DB::connection()->getPdo();
            $rows[] = $this->row('system_health.rows.db_connection', __('system_health.values.connected'), 'ok');

            $driver = (string) DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                $version = (string) (DB::selectOne('select version() as v')->v ?? '');
                // The leading numeric is the server version. MySQL 8+ is the
                // target; MariaDB embeds its own version but is supported too.
                preg_match('/^(\d+)\./', $version, $m);
                $major   = (int) ($m[1] ?? 0);
                $isMaria = str_contains(strtolower($version), 'maria');
                $healthy = $major >= 8 || $isMaria;
                $rows[]  = $this->row(
                    'system_health.rows.db_version',
                    $version ?: $driver,
                    $healthy ? 'ok' : 'warn',
                    $healthy ? null : 'system_health.hints.db_version',
                );
            } else {
                $rows[] = $this->row('system_health.rows.db_driver', $driver, 'warn', 'system_health.hints.db_driver');
            }
        } catch (\Throwable $e) {
            $rows[] = $this->row('system_health.rows.db_connection', __('system_health.values.failed'), 'fail', 'system_health.hints.db_connection');
        }

        return ['title' => __('system_health.groups.database'), 'rows' => $rows];
    }

    /** @return array<string,mixed> */
    private function storage(): array
    {
        $storageWritable = is_writable(storage_path());
        $cacheWritable   = is_writable(base_path('bootstrap/cache'));
        $linkPath        = public_path('storage');
        $linkOk          = is_link($linkPath) || is_dir($linkPath);

        // Free disk space on the partition holding the app.
        $free  = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        $freePct = ($free && $total) ? ($free / $total) * 100 : null;

        return [
            'title' => __('system_health.groups.storage'),
            'rows'  => [
                $this->row(
                    'system_health.rows.storage_writable',
                    $storageWritable ? __('system_health.values.writable') : __('system_health.values.not_writable'),
                    $storageWritable ? 'ok' : 'fail',
                    $storageWritable ? null : 'system_health.hints.storage_writable',
                ),
                $this->row(
                    'system_health.rows.cache_writable',
                    $cacheWritable ? __('system_health.values.writable') : __('system_health.values.not_writable'),
                    $cacheWritable ? 'ok' : 'fail',
                    $cacheWritable ? null : 'system_health.hints.cache_writable',
                ),
                $this->row(
                    'system_health.rows.storage_link',
                    $linkOk ? __('system_health.values.present') : __('system_health.values.missing'),
                    $linkOk ? 'ok' : 'warn',
                    $linkOk ? null : 'system_health.hints.storage_link',
                ),
                $this->row(
                    'system_health.rows.disk_free',
                    $free ? $this->humanBytes((int) $free).($freePct !== null ? ' ('.number_format($freePct, 0).'%)' : '') : '—',
                    $freePct === null ? 'ok' : ($freePct < 5 ? 'fail' : ($freePct < 15 ? 'warn' : 'ok')),
                    ($freePct !== null && $freePct < 15) ? 'system_health.hints.disk_free' : null,
                ),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function jobs(): array
    {
        $rows   = [];
        $driver = (string) config('queue.default');

        $rows[] = $this->row(
            'system_health.rows.queue_driver',
            $driver,
            $driver === 'database' || $driver === 'sync' ? 'ok' : 'warn',
            in_array($driver, ['database', 'sync'], true) ? null : 'system_health.hints.queue_driver',
        );

        // Pending jobs + how stale the oldest is — a backlog of old jobs is the
        // tell-tale sign the cron worker (schedule:run / queue:work) isn't firing.
        if (Schema::hasTable('jobs')) {
            try {
                $pending   = (int) DB::table('jobs')->count();
                $oldestTs  = DB::table('jobs')->min('available_at') ?? DB::table('jobs')->min('created_at');
                $staleMins = $oldestTs ? (int) round((time() - (int) $oldestTs) / 60) : 0;
                $stale     = $pending > 0 && $staleMins >= 5;

                $rows[] = $this->row(
                    'system_health.rows.jobs_pending',
                    $pending > 0 ? trans_choice('system_health.values.jobs_pending', $pending, ['count' => $pending, 'mins' => $staleMins]) : __('system_health.values.none'),
                    $stale ? 'warn' : 'ok',
                    $stale ? 'system_health.hints.jobs_stale' : null,
                );
            } catch (\Throwable) {
                // table exists but unreadable — skip silently
            }
        }

        if (Schema::hasTable('failed_jobs')) {
            try {
                $failed = (int) DB::table('failed_jobs')->count();
                $rows[] = $this->row(
                    'system_health.rows.jobs_failed',
                    (string) $failed,
                    $failed > 0 ? 'warn' : 'ok',
                    $failed > 0 ? 'system_health.hints.jobs_failed' : null,
                );
            } catch (\Throwable) {
                // skip
            }
        }

        return ['title' => __('system_health.groups.jobs'), 'rows' => $rows];
    }

    /**
     * PHP / server requirements — reuses the installer's environment probe so
     * the rules stay in one place. Statuses are normalised (`pass` → `ok`).
     *
     * @return array<string,mixed>
     */
    private function phpServer(): array
    {
        $rows = [];
        foreach (($this->requirements)()['checks'] as $c) {
            $rows[] = [
                'label'  => ($c['group'] ?? '').' · '.($c['label'] ?? ''),
                'value'  => (string) ($c['value'] ?? ''),
                'status' => ($c['status'] ?? 'ok') === 'pass' ? 'ok' : (string) $c['status'],
                'hint'   => $c['hint'] ?? null,   // already a human string from CheckRequirements
            ];
        }

        return ['title' => __('system_health.groups.php'), 'rows' => $rows];
    }

    /** @return array<string,mixed> */
    private function integrations(): array
    {
        $mailHost  = (string) config('mail.mailers.smtp.host', '');
        $pusherKey = (string) config('broadcasting.connections.pusher.key', '');

        return [
            'title' => __('system_health.groups.integrations'),
            'rows'  => [
                $this->row(
                    'system_health.rows.mail',
                    $mailHost !== '' ? $mailHost : __('system_health.values.not_configured'),
                    $mailHost !== '' ? 'ok' : 'warn',
                    $mailHost !== '' ? null : 'system_health.hints.mail',
                ),
                $this->row(
                    'system_health.rows.realtime',
                    $pusherKey !== '' ? __('system_health.values.configured') : __('system_health.values.polling'),
                    'ok',
                    null,
                ),
            ],
        ];
    }

    /**
     * Build a row. `$labelKey` and `$hintKey` are lang keys; `$value` is an
     * already-resolved display string.
     *
     * @return array{label:string, value:string, status:string, hint:?string}
     */
    private function row(string $labelKey, string $value, string $status, ?string $hintKey = null): array
    {
        return [
            'label'  => __($labelKey),
            'value'  => $value,
            'status' => $status,
            'hint'   => $hintKey ? __($hintKey) : null,
        ];
    }

    /**
     * Roll the per-row statuses into an overall verdict.
     *
     * @param  list<array<string,mixed>>  $groups
     * @return array{ok:int, warn:int, fail:int, status:string}
     */
    private function summarize(array $groups): array
    {
        $ok = $warn = $fail = 0;
        foreach ($groups as $g) {
            foreach ($g['rows'] as $r) {
                match ($r['status']) {
                    'fail'  => $fail++,
                    'warn'  => $warn++,
                    default => $ok++,
                };
            }
        }

        return [
            'ok'     => $ok,
            'warn'   => $warn,
            'fail'   => $fail,
            'status' => $fail > 0 ? 'fail' : ($warn > 0 ? 'warn' : 'ok'),
        ];
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return round($n, $i >= 3 ? 1 : 0).' '.$units[$i];
    }
}
