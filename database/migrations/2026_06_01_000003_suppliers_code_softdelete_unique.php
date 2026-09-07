<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make `suppliers.code` unique only among LIVE rows.
 *
 * Mirrors the customers fix: single-column UNIQUE on `code` permanently
 * blocks a code as soon as one supplier with that code is soft-deleted,
 * even though the validator (which honours `whereNull('deleted_at')`)
 * accepts the value — result: silent `UniqueConstraintViolationException`
 * on later inserts/updates. Switching to a composite UNIQUE on
 * (`code`, `deleted_at`) restores the intended behaviour.
 *
 * Idempotent: fresh installs already get the composite unique from the
 * source migration; this only runs the swap when the old single-column
 * index is still present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->indexExists('suppliers', 'suppliers_code_unique')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropUnique('suppliers_code_unique');
            });
        }

        if (! $this->indexExists('suppliers', 'suppliers_code_deleted_at_unique')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->unique(['code', 'deleted_at'], 'suppliers_code_deleted_at_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('suppliers', 'suppliers_code_deleted_at_unique')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropUnique('suppliers_code_deleted_at_unique');
            });
        }

        if (! $this->indexExists('suppliers', 'suppliers_code_unique')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->unique('code', 'suppliers_code_unique');
            });
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return (bool) DB::selectOne(
                "SELECT name FROM sqlite_master WHERE type='index' AND name = ?",
                [$name]
            );
        }

        // MySQL / MariaDB
        return (bool) DB::selectOne(
            "SELECT 1 FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name = ?
                AND index_name = ?
              LIMIT 1",
            [$table, $name]
        );
    }
};
