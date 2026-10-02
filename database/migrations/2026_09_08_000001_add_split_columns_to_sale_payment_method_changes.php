<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `old_amount`/`amount` to `sale_payment_method_changes` so the same
 * audit table can also cover {@see \App\Actions\Sales\SplitSalePayment} —
 * turning one closed-sale tender into several across different methods
 * (e.g. a single 95.79 card payment becomes 60.00 card + 35.79 cash).
 * Both nullable: a plain method-only swap ({@see \App\Actions\Sales\ChangeSalePaymentMethod})
 * never touches an amount and leaves these null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_payment_method_changes', function (Blueprint $table) {
            $table->decimal('old_amount', 15, 4)->nullable()->after('new_payment_method_id');
            $table->decimal('amount', 15, 4)->nullable()->after('old_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sale_payment_method_changes', function (Blueprint $table) {
            $table->dropColumn(['old_amount', 'amount']);
        });
    }
};
