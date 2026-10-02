<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per cashier action on the /cashier POS screen — cart edits,
 * price checks, discounts, hold/void, drawer kicks, shift open/close,
 * X/Z report prints, checkout, refunds, login/logout, and client-side
 * JS errors. A point-in-time event log (no `updated_at` — nothing here
 * is ever edited), grouped by `type` for the by-type report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('terminal_id')->nullable()->constrained('terminals')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            // Broad bucket for the by-type report — 'cart', 'discount',
            // 'sale', 'refund', 'drawer', 'shift', 'print', 'auth', 'error'.
            $table->string('type', 32)->index();
            // Specific event within the bucket — 'cart.add', 'cart.remove',
            // 'cart.qty_change', 'sale.complete', 'drawer.open_no_sale',
            // 'shift.open', 'shift.close', 'print.x_report', 'auth.login',
            // 'js_error', etc.
            $table->string('action', 64)->index();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            // Free-form event detail — product name/qty for a cart edit,
            // amount for a discount, error message + stack for js_error.
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reference_type', 'reference_id']);
            $table->index(['store_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_activity_logs');
    }
};
