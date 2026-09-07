<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make `customers.code` unique only among LIVE rows.
 *
 * The original schema used a single-column UNIQUE on `code`. That
 * conflicts with soft-deletes — a customer soft-deleted with code
 * `C-000009` permanently blocks any later customer from using
 * `C-000009`, even though the validator (which honours
 * `whereNull('deleted_at')`) lets the value through. Result: silent
 * `UniqueConstraintViolationException` on update.
 *
 * Switching to a composite UNIQUE on (`code`, `deleted_at`) restores
 * the intended behaviour because MySQL treats NULLs as distinct in
 * unique indexes — so multiple soft-deleted rows can share a code,
 * but two live rows (deleted_at IS NULL) still cannot.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: fresh installs already get the composite unique
        // from the source migration, so this only runs the swap when
        // the old single-column index is still present.
        if ($this->indexExists('customers', 'customers_code_unique')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropUnique('customers_code_unique');
            });
        }

        if (! $this->indexExists('customers', 'customers_code_deleted_at_unique')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unique(['code', 'deleted_at'], 'customers_code_deleted_at_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('customers', 'customers_code_deleted_at_unique')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropUnique('customers_code_deleted_at_unique');
            });
        }

        if (! $this->indexExists('customers', 'customers_code_unique')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unique('code', 'customers_code_unique');
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
