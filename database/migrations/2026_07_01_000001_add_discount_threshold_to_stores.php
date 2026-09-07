<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-store discount governance (Checkout discounts, Slice 1). Discounts
 * at or below this percent can be applied by anyone with `sales.discount`;
 * above it needs `sales.discount_above_threshold` (or a manager approval).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('discount_threshold_percent', 5, 2)->default(10)->after('enforce_shifts');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('discount_threshold_percent');
        });
    }
};
