<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 4b — supplier-payment void.
 *
 * Voiding doesn't delete the row (history is preserved for audit + journal
 * reversal); we soft-mark it with `voided_at`/`voided_by` and an optional
 * `void_reason`. The action layer reverses the cash effect (PO balance,
 * supplier outstanding) inside its own transaction; this migration is
 * just the marker columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('notes');
            $table->foreignId('voided_by')->nullable()->after('voided_at')
                ->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable()->after('voided_by');

            // Indexed because the index/show queries filter "active only"
            // (voided rows render muted) and the supplier-statement view
            // needs to skip voided rows for balance math.
            $table->index('voided_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropIndex(['voided_at']);
            $table->dropColumn(['voided_at', 'voided_by', 'void_reason']);
        });
    }
};
