<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Swap `purchase_payments.client_uuid` from UNIQUE to plain INDEX.
 *
 * `client_uuid` groups the N rows that came from one user submission
 * (one allocation row per affected purchase, sharing a uuid). The
 * original migration declared it UNIQUE — which made every multi-PO
 * payment fail on the second insert with a duplicate-key error, with
 * the whole transaction rolling back.
 *
 * Idempotent — fresh installs already get the index-only form from
 * the source migration; this only runs the swap when the old UNIQUE
 * index is still present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->indexExists('purchase_payments', 'purchase_payments_client_uuid_unique')) {
            Schema::table('purchase_payments', function (Blueprint $table) {
                $table->dropUnique('purchase_payments_client_uuid_unique');
            });
        }

        if (! $this->indexExists('purchase_payments', 'purchase_payments_client_uuid_index')) {
            Schema::table('purchase_payments', function (Blueprint $table) {
                $table->index('client_uuid');
            });
        }
    }

    public function down(): void
    {
        // Down deliberately doesn't restore the unique — restoring it
        // would re-break every multi-PO payment in the DB. If a downgrade
        // is genuinely needed, drop the index manually.
        if ($this->indexExists('purchase_payments', 'purchase_payments_client_uuid_index')) {
            Schema::table('purchase_payments', function (Blueprint $table) {
                $table->dropIndex('purchase_payments_client_uuid_index');
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

        return (bool) DB::selectOne(
            "SELECT 1 FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name   = ?
                AND index_name   = ?
              LIMIT 1",
            [$table, $name]
        );
    }
};
