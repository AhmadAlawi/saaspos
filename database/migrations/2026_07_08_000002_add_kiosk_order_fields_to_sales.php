<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-Ordering Kiosk — Slice 2 (`order` mode).
 *
 * A kiosk `order`-mode submission becomes a first-class pending sale
 * (`status = placed`) that shows up in the staff orders queue and is paid
 * at the counter. These columns carry the kiosk-specific metadata:
 *   - `origin`      — where the sale came from (cashier | kiosk | …). Lets
 *                     reports tell counter sales from kiosk ones.
 *   - `placed_at`   — when the customer submitted it.
 *   - `pickup_code` — the short human code the customer is called by.
 *   - `kiosk_note`  — the customer's optional order note.
 * See docs/features/kiosk-self-ordering.md §4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('origin', 16)->default('cashier')->after('status');
            $table->timestamp('placed_at')->nullable()->after('held_at');
            $table->string('pickup_code', 32)->nullable()->after('placed_at');
            $table->string('kiosk_note')->nullable()->after('pickup_code');

            $table->index(['store_id', 'origin']);
            $table->index('pickup_code');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'origin']);
            $table->dropIndex(['pickup_code']);
            $table->dropColumn(['origin', 'placed_at', 'pickup_code', 'kiosk_note']);
        });
    }
};
