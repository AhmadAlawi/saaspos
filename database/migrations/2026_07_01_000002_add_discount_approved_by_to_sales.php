<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the manager who approved an over-threshold discount at the
 * cashier (Checkout discounts, Slice 2). Null for discounts within the
 * threshold or applied by a user who holds `sales.discount_above_threshold`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('discount_approved_by')->nullable()->after('discount_total')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_approved_by');
        });
    }
};
