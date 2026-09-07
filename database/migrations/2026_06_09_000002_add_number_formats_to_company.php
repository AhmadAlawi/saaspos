<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Number-format templates per number type (sale, hold). Defaults baked
 * into Company::numberFormat() so existing installs keep generating
 * `SALE-MAIN-YYYYMM-NNNN` without any backfill.
 *
 * The format string supports these placeholders:
 *   {store}      — store code, uppercase alphanum
 *   {Y} {y}      — 4 / 2-digit year
 *   {m} {d}      — 2-digit month / day
 *   {Ym} {Ymd}   — combined year-month / year-month-day
 *   {seq:N}      — zero-padded sequence (N = pad width). Must be last.
 *   {seq}        — unpadded sequence.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->string('sale_number_format', 191)->nullable()->after('cashier_settings');
            $table->string('hold_number_format', 191)->nullable()->after('sale_number_format');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn(['sale_number_format', 'hold_number_format']);
        });
    }
};
