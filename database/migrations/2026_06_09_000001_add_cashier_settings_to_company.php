<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashier (POS) UI preferences. One JSON column on `company` keeps the
 * surface small — the cashier-page Alpine factory and the Blade template
 * read these via Company::current()->cashier(). Defaults come from the
 * accessor so existing rows don't need backfill.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->json('cashier_settings')->nullable()->after('receipt_return_policy');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn('cashier_settings');
        });
    }
};
