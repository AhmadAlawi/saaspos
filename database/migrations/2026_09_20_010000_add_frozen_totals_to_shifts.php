<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `shifts.sales_total`/`refunds_total`/`payment_totals` are frozen at
 * close time, but the OTHER fields ComputeShiftTotals returns
 * (tax_total, discount_total, cash_sales, cash_refunds, pay_ins,
 * pay_outs, supplier payouts, drawer_open_no_sale_count) never were —
 * so a Z-report reprint for a closed shift had no choice but to
 * recompute those live, which drifted the moment any sale on that
 * shift changed status afterward (e.g. a refund made the next day).
 * One JSON snapshot of the full ComputeShiftTotals() array, frozen
 * once at close, lets the reprint use it wholesale instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->json('frozen_totals')->nullable()->after('payment_totals');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('frozen_totals');
        });
    }
};
