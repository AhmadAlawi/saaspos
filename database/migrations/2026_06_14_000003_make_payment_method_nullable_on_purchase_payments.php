<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow `purchase_payments.payment_method_id` to be null.
 *
 * Supplier-credit rows (purchase_id IS NULL — the overflow leftover
 * when the cashier pays more than the allocated total) are NOT real
 * cash movements; they're a residual balance carried on the supplier
 * account. Until now they inherited the originating payment's method
 * + reference, which mis-reported "₹X paid via Cash" on a row that
 * isn't a payment at all. Make the FK nullable so the credit row can
 * cleanly omit it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->foreignId('payment_method_id')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->foreignId('payment_method_id')
                ->nullable(false)
                ->change();
        });
    }
};
