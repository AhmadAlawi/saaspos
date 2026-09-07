<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of the cashier's cart at QR-generation time, so the
 * customer's pay page can show what they're paying for (item names,
 * qty, line totals) — not just a bare amount. Captured when the
 * session is created; never edited afterwards.
 *
 * Stored as JSON: an array of { name, quantity, unit_price, line_total }
 * plus a small totals block. Customer-facing only — no internal IDs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_payment_sessions', function (Blueprint $table) {
            $table->json('cart_summary')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('pos_payment_sessions', function (Blueprint $table) {
            $table->dropColumn('cart_summary');
        });
    }
};
