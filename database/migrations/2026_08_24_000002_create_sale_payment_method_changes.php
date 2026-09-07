<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for {@see \App\Actions\Sales\ChangeSalePaymentMethod} — a
 * manager-only correction to which payment method a completed sale's
 * tender used, allowed even after the sale's shift has closed and its
 * Z-report already printed (see that Action's docblock). The
 * `sale_payments` row itself is mutated in place rather than replaced,
 * same as VoidSale keeps the sale row rather than deleting it — this
 * table is the only record of what it used to say, who changed it, and
 * why, since a closed shift's Z-report won't retroactively regenerate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payment_method_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('sale_payment_id')->constrained('sale_payments')->cascadeOnDelete();
            $table->foreignId('old_payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('new_payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payment_method_changes');
    }
};
