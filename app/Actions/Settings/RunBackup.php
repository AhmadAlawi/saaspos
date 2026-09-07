<?php

namespace App\Actions\Settings;

use App\Models\BackupLog;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Creates a single restore-grade `.zip` backup and records the `backup_logs`
 * row that describes it.
 *
 * Archive layout (matches the restore wizard + the backup-restore spec):
 *
 *   backup-…zip
 *   ├── manifest.json          — unencrypted metadata (versions, stats, checksum)
 *   ├── database/dump.sql      — DROP + CREATE + INSERT for every table, FK checks
 *   │                            disabled so table order doesn't matter
 *   ├── storage/app/public/…   — mirror of storage/app/public  (when include_uploads)
 *   └── public/uploads/…       — mirror of public/uploads       (when present)
 *
 * The dump is *schema-aware* — not INSERT-only — precisely so a restore (and the
 * updater's rollback) can rebuild the database from scratch, including whatever
 * schema the migrations produced. It is also driver-aware: MySQL (production)
 * and SQLite (the test suite) differ in identifier quoting, string escaping and
 * the foreign-key guard, and getting those wrong silently corrupts a restore.
 *
 * Returns the persisted BackupLog. No `mysqldump` dependency — everything is
 * generated in PHP so it runs on shared hosting.
 */
class RunBackup
{
    /**
     * Tables whose ROWS we never dump — transient queue/cache/session plumbing
     * that re-creates itself. Their schema is still emitted so the restored
     * database is structurally complete. `migrations` is deliberately absent:
     * its rows must survive a restore so the schema version is preserved and a
     * subsequent `migrate` doesn't try to re-run everything from zero.
     */
    private const SKIP_DATA_TABLES = [
        'cache', 'cache_locks', 'sessions', 'jobs', 'failed_jobs', 'job_batches',
        'password_reset_tokens',
    ];

    /** Rows per INSERT statement — small enough to stay readable on shared MySQL. */
    private const ROWS_PER_INSERT = 100;

    /**
     * @param  string    $type    'manual' | 'scheduled' | 'pre-update' | 'pre-restore'
     * @param  int|null  $userId  initiating user, or null for the scheduler / system
     */
    public function __invoke(string $type = 'manual', ?int $userId = null): BackupLog
    {
        $settings = app_backup();
        $diskName = $this->resolveDisk($settings['target_disk']);

        do_action('backup.before_start', $type);

        $log = BackupLog::create([
            'type'        => $type,
            'destination' => $diskName,
            'started_at'  => now(),
            'status'      => 'running',
            'created_by'  => $userId,
        ]);

        try {
            [$path, $size, $checksum, $manifest] = $this->build($settings, $diskName);

            $log->update([
                'finished_at'     => now(),
                'status'          => 'success',
                'file_path'       => $path,
                'file_size_bytes' => $size,
                'checksum'        => $checksum,
                'manifest'        => $manifest,
            ]);

            if ($company = Company::current()) {
                $company->update(['backup_last_run_at' => now()]);
            }

            do_action('backup.completed', $log);

            return $log;
        } catch (\Throwable $e) {
            $log->update([
                'finished_at'   => now(),
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            do_action('backup.failed', $log, $e);

            throw $e;
        }
    }

    /**
     * Assemble the archive on disk.
     *
     * @param  array<string,mixed>  $settings
     * @return array{0:string,1:int,2:string,3:array<string,mixed>}  [relativePath, sizeBytes, sha256, manifest]
     */
    private function build(array $settings, string $diskName): array
    {
        $tmpZip = tempnam(sys_get_temp_dir(), 'pos-backup-');

        $zip = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create backup archive.');
        }

        [$sql, $stats, $skipped] = $this->dumpDatabase();
        $checksum = hash('sha256', $sql);
        $zip->addFromString('database/dump.sql', $sql);

        $storage = $settings['include_uploads'] ? $this->addFiles($zip) : ['count' => 0, 'bytes' => 0];

        $manifest = $this->buildManifest($sql, $checksum, $stats, $storage, $skipped);
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zip->close();

        $size     = (int) filesize($tmpZip);
        $filename = $this->filename();
        Storage::disk($diskName)->putFileAs('backups', new \Illuminate\Http\File($tmpZip), $filename);
        @unlink($tmpZip);

        return ['backups/'.$filename, $size, $checksum, $manifest];
    }

    private function resolveDisk(string $name): string
    {
        return array_key_exists($name, config('filesystems.disks', [])) ? $name : 'local';
    }

    private function filename(): string
    {
        $slug = Str::slug(config('app.name')) ?: 'pos';
        return sprintf('%s-%s.zip', $slug, now()->format('Ymd-His'));
    }

    /**
     * Generate the full SQL dump and collect per-table row counts for the
     * manifest. Each table emits `DROP TABLE` + `CREATE TABLE` + `INSERT`s
     * (rows skipped for the transient tables in SKIP_DATA_TABLES).
     *
     * Per-table work is wrapped so a single problematic entry — an orphaned
     * view, a cross-schema listing, or a table dropped between listing and dump
     * — is recorded and skipped instead of aborting the whole backup. Each
     * table's SQL is assembled in a local buffer and only appended on success,
     * so a mid-table failure never leaves half a statement in the dump.
     *
     * @return array{0:string,1:array<string,int>,2:array<string,string>}  [sql, stats, skipped]
     */
    /**
     * Base tables in THIS connection's database only.
     *
     * `Schema::getTableListing()` can return tables from OTHER databases on the
     * same MySQL server when the connection user has cross-database privileges
     * (common on shared hosting). That produced corrupt dumps — a CREATE from
     * our DB paired with INSERT columns/rows from a same-named table in another
     * DB (e.g. a foreign `brands`), and `SHOW CREATE TABLE` failures for tables
     * that only exist elsewhere (e.g. `addon_groups`). Scoping strictly to the
     * current schema, base tables only (no views), fixes both.
     *
     * @return array<int, string>
     */
    private function tableListing(string $driver): array
    {
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            // SQLite (tests) etc. — single-file database, no cross-schema risk.
            return Schema::getTableListing();
        }

        $database = DB::connection()->getDatabaseName();
        $rows = DB::select(
            'SELECT TABLE_NAME AS name FROM information_schema.tables '
            ."WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'",
            [$database]
        );

        return array_map(static fn ($r) => $r->name, $rows);
    }

    private function dumpDatabase(): array
    {
        $driver = DB::connection()->getDriverName();
        $tables = $this->tableListing($driver);

        $out  = "-- Backup generated ".now()->toIso8601String()."\n";
        $out .= "-- App: ".config('app.name')."\n\n";
        $out .= $driver === 'sqlite'
            ? "PRAGMA foreign_keys=OFF;\n\n"
            : "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        $stats   = [];
        $skipped = [];

        foreach ($tables as $table) {
            // SQLite's getTableListing() can include the schema prefix
            // (e.g. `main.company`) — strip it so the statements target the
            // bare table name a fresh database would have.
            $bare = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;

            try {
                $create = $this->createStatement($driver, $bare);
                if ($create === null) {
                    continue; // internal table (e.g. sqlite_sequence) — no portable schema
                }

                $tableSql  = "-- {$bare}\n";
                $tableSql .= 'DROP TABLE IF EXISTS '.$this->quoteIdentifier($driver, $bare).";\n";
                $tableSql .= rtrim($create, "; \n").";\n\n";

                if (! in_array($bare, self::SKIP_DATA_TABLES, true)) {
                    $cols = Schema::getColumnListing($table);
                    if (! empty($cols)) {
                        $count = DB::table($table)->count();
                        $stats[$bare] = $count;
                        if ($count > 0) {
                            $quotedCols = '`'.implode('`, `', $cols).'`';
                            $buffer = '';
                            DB::table($table)->orderByRaw('1')->chunk(self::ROWS_PER_INSERT, function ($rows) use (&$buffer, $bare, $quotedCols, $cols, $driver) {
                                $values = [];
                                foreach ($rows as $row) {
                                    $row   = (array) $row;
                                    $cells = [];
                                    foreach ($cols as $c) {
                                        $cells[] = $this->escape($driver, $row[$c] ?? null);
                                    }
                                    $values[] = '('.implode(', ', $cells).')';
                                }
                                $buffer .= "INSERT INTO `{$bare}` ({$quotedCols}) VALUES\n  ".implode(",\n  ", $values).";\n\n";
                            });
                            $tableSql .= $buffer;
                        }
                    }
                }

                $out .= $tableSql;
            } catch (\Throwable $e) {
                // Don't let one bad table (orphaned view, stale listing, perms)
                // fail the entire backup — note it and carry on.
                $reason = str_replace(["\n", "\r"], ' ', $e->getMessage());
                $skipped[$bare] = $reason;
                unset($stats[$bare]);
                $out .= "-- skipped {$bare}: {$reason}\n\n";
            }
        }

        $out .= $driver === 'sqlite' ? "PRAGMA foreign_keys=ON;\n" : "SET FOREIGN_KEY_CHECKS=1;\n";

        return [$out, $stats, $skipped];
    }

    /**
     * The `CREATE TABLE` statement for a table, or null when there's no
     * portable schema to emit (SQLite-internal tables).
     */
    private function createStatement(string $driver, string $table): ?string
    {
        if ($driver === 'sqlite') {
            $row = DB::selectOne(
                "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$table]
            );
            return $row->sql ?? null;
        }

        // MySQL / MariaDB — `SHOW CREATE TABLE` returns a `Create Table` column.
        $row = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
        return $row['Create Table'] ?? null;
    }

    private function quoteIdentifier(string $driver, string $name): string
    {
        return $driver === 'sqlite' ? '"'.$name.'"' : '`'.$name.'`';
    }

    /**
     * Quote a value for a SQL literal. Driver-aware because MySQL interprets
     * backslash escapes inside string literals and SQLite does not — escaping
     * backslashes for SQLite would double them on restore.
     */
    private function escape(string $driver, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $s = (string) $value;

        if ($driver === 'sqlite') {
            // SQLite: only single-quote doubling; backslash is a literal byte.
            return "'".str_replace("'", "''", $s)."'";
        }

        // MySQL / MariaDB: escape backslash, quote and the null byte.
        $s = str_replace(['\\', "'", "\0"], ['\\\\', "''", '\\0'], $s);
        return "'".$s."'";
    }

    /**
     * Mirror the user file trees into the archive.
     *
     * @return array{count:int,bytes:int}
     */
    private function addFiles(ZipArchive $zip): array
    {
        $count = 0;
        $bytes = 0;

        $trees = [
            storage_path('app/public') => 'storage/app/public',
            public_path('uploads')      => 'public/uploads',
        ];

        foreach ($trees as $root => $prefix) {
            if (! is_dir($root)) {
                continue;
            }

            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iter as $file) {
                if ($file->isDir()) {
                    continue;
                }
                $rel = $prefix.'/'.str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $zip->addFile($file->getPathname(), $rel);
                $count++;
                $bytes += $file->getSize();
            }
        }

        return ['count' => $count, 'bytes' => $bytes];
    }

    /**
     * The always-unencrypted manifest. The restore wizard reads this to show
     * the operator what's inside, and to gate compatibility (app + schema
     * version) before touching the live database.
     *
     * @param  array<string,int>          $stats
     * @param  array{count:int,bytes:int} $storage
     * @param  array<string,string>       $skipped  tables skipped (name => reason)
     * @return array<string,mixed>
     */
    private function buildManifest(string $sql, string $checksum, array $stats, array $storage, array $skipped = []): array
    {
        $company = Company::current();

        return [
            'backup_format_version' => '1',
            'created_at'     => now()->toIso8601String(),
            'app_name'       => config('app.name'),
            'app_version'    => config('pos.version', '1.0.0'),
            'schema_version' => $this->schemaVersion(),
            'driver'         => DB::connection()->getDriverName(),
            'install_id'     => $this->installId(),
            'company'        => $company ? [
                'name'          => $company->name,
                'country'       => $company->country_code,
                'base_currency' => $company->base_currency_code,
                'industries'    => $company->industries_enabled ?? [],
            ] : null,
            'stats'          => $stats,
            'skipped_tables' => $skipped,
            'files'          => [
                'database/dump.sql' => [
                    'size_bytes' => strlen($sql),
                    'sha256'     => $checksum,
                ],
                'storage_files_count' => $storage['count'],
                'storage_total_bytes' => $storage['bytes'],
            ],
            'encryption'     => [
                'encrypted' => false,
            ],
        ];
    }

    /** The newest applied migration name — the restore's compatibility anchor. */
    private function schemaVersion(): string
    {
        try {
            return (string) (DB::table('migrations')->max('migration') ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    /** Stable, secret-free identifier for this install (manifest display only). */
    private function installId(): string
    {
        $slug = Str::slug(config('app.name')) ?: 'pos';
        return $slug.'-'.substr(md5((string) config('app.url')), 0, 8);
    }
}
