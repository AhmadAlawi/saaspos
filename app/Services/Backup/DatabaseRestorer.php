<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Replaces the live database with the contents of a backup's `dump.sql`.
 *
 * There is no transaction wrapping this — DDL is auto-committed on MySQL, so a
 * mid-restore failure can't be rolled back at the database level. The restore
 * wizard's safety net is the pre-restore snapshot, not a transaction.
 *
 * Driver-aware (MySQL production, SQLite tests) for the foreign-key guard and
 * identifier quoting. The dump is executed whole via `unprepared()` so the PDO
 * driver parses statement boundaries correctly — naive `;`-splitting would
 * break on semicolons and newlines living inside string values.
 */
class DatabaseRestorer
{
    /**
     * Drop every table currently in the database — including any not present in
     * the backup (e.g. a table a half-applied update added). The dump then
     * recreates exactly the tables that existed at backup time.
     */
    public function dropAllTables(): void
    {
        $driver = DB::connection()->getDriverName();
        $tables = $this->currentSchemaTables($driver);

        if ($driver === 'sqlite') {
            DB::unprepared('PRAGMA foreign_keys=OFF;');
            foreach ($tables as $table) {
                $bare = $this->bare($table);
                DB::statement('DROP TABLE IF EXISTS "'.$bare.'"');
            }
            DB::unprepared('PRAGMA foreign_keys=ON;');

            return;
        }

        DB::unprepared('SET FOREIGN_KEY_CHECKS=0;');
        foreach ($tables as $table) {
            DB::statement('DROP TABLE IF EXISTS `'.$this->bare($table).'`');
        }
        DB::unprepared('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function restore(string $sqlPath): void
    {
        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            throw new RuntimeException('Could not read the backup database dump.');
        }

        // The dump carries its own FK guard (SET FOREIGN_KEY_CHECKS / PRAGMA),
        // so table order inside the file doesn't matter.
        DB::connection()->unprepared($sql);
    }

    /**
     * Read every row of the given tables so they can be re-applied after the
     * restore. Used to keep operational/audit tables (backup history, restore
     * history, update history) continuous across a restore instead of
     * reverting them to their backup-time state.
     *
     * @param  string[]  $tables
     * @return array<string, array<int, array<string,mixed>>>
     */
    public function snapshot(array $tables): array
    {
        $out = [];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $out[$table] = array_map(static fn ($r) => (array) $r, DB::table($table)->get()->all());
            }
        }

        return $out;
    }

    /**
     * Re-apply a {@see snapshot()} on top of the freshly restored data,
     * upserting by id. The backup's rows form the base; these live rows win,
     * so anything that happened after the backup (including the in-flight
     * restore's own log row) survives. Foreign-key checks are disabled because
     * a preserved row may reference an entity that only exists post-backup.
     *
     * @param  array<string, array<int, array<string,mixed>>>  $snapshot
     */
    public function overlay(array $snapshot): void
    {
        $driver = DB::connection()->getDriverName();
        DB::unprepared($driver === 'sqlite' ? 'PRAGMA foreign_keys=OFF;' : 'SET FOREIGN_KEY_CHECKS=0;');

        try {
            foreach ($snapshot as $table => $rows) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                foreach ($rows as $row) {
                    if (! array_key_exists('id', $row)) {
                        continue;
                    }
                    DB::table($table)->updateOrInsert(['id' => $row['id']], $row);
                }
            }
        } finally {
            DB::unprepared($driver === 'sqlite' ? 'PRAGMA foreign_keys=ON;' : 'SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    private function bare(string $table): string
    {
        return str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
    }

    /**
     * Base tables in THIS connection's database only — mirrors the scoping in
     * RunBackup. `Schema::getTableListing()` can leak tables from OTHER
     * databases on a shared MySQL server, so we query information_schema scoped
     * to the current schema (base tables only, no views).
     *
     * @return array<int, string>
     */
    private function currentSchemaTables(string $driver): array
    {
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
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
}
