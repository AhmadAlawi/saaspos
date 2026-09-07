<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the "Store Credit" payment method from the manual cashier
 * tile list. We deactivate rather than delete because existing
 * sale_payments / customer_payments rows could reference it as a
 * foreign key; flipping is_active=false hides it from the cashier
 * UI while keeping referential integrity intact.
 *
 * The seeder no longer ships a `store_credit` row, so fresh installs
 * never see it; this migration cleans up upgrades.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('payment_methods')
            ->where('code', 'store_credit')
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        // Best-effort reactivation — keeps the seeder shape if the
        // row still exists. New installs won't have it at all, in
        // which case this is a no-op.
        DB::table('payment_methods')
            ->where('code', 'store_credit')
            ->update(['is_active' => true]);
    }
};
