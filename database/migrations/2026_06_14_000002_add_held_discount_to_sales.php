<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist the order-level discount on a held sale so the cashier
 * can resume the cart with the original discount intact.
 *
 * The cashier's discount lives in two parts:
 *   - `type`  : 'pct' (percent) or 'amt' (currency)
 *   - `value` : raw percent (0-100) or raw currency amount
 *
 * Stored as a small JSON blob (one column, both fields) so a future
 * Slice can extend the shape (per-line allocation, named discounts)
 * without another migration. Null for holds without any discount.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->json('held_discount')->nullable()->after('held_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('held_discount');
        });
    }
};
