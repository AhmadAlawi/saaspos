<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a cash-drawer pay-out (or its reversing pay-in) to the supplier payment
 * that produced it. A supplier payment is a group of `purchase_payments` sharing
 * a `client_uuid`, so we store that uuid rather than a single-row FK — mirroring
 * the existing `expense_id` link, one level up.
 *
 * Lets a cash supplier payment made during an open shift reduce the till's
 * expected cash, and lets a void of that payment put the cash back.
 *
 * Idempotent: re-run safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_drawer_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_drawer_entries', 'supplier_payment_uuid')) {
                $table->string('supplier_payment_uuid', 36)->nullable()->after('expense_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_drawer_entries', function (Blueprint $table) {
            if (Schema::hasColumn('cash_drawer_entries', 'supplier_payment_uuid')) {
                $table->dropIndex(['supplier_payment_uuid']);
                $table->dropColumn('supplier_payment_uuid');
            }
        });
    }
};
