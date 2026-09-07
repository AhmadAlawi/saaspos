<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discount reason category (Checkout discounts, Slice 3). Complements the
 * free-text `reason` with a coarse, reportable bucket (loyalty, damaged,
 * price-match, …).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_discounts', function (Blueprint $table) {
            $table->string('reason_category', 32)->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('sale_discounts', function (Blueprint $table) {
            $table->dropColumn('reason_category');
        });
    }
};
