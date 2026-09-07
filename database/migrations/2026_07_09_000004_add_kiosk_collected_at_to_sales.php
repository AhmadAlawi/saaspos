<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a kiosk shopper pays at the machine, the sale is `completed` but the
 * goods are still behind the counter. That sale appeared in no staff queue —
 * `/admin/kiosk-orders` lists only `placed` orders — so a customer walking up
 * with pickup code "K007" had nothing staff could match them against.
 *
 * `kiosk_collected_at` closes the loop: a paid kiosk sale sits in an
 * "awaiting collection" list until staff hand the goods over and stamp it.
 * NULL + paid + kiosk origin = still on the shelf.
 *
 * See docs/features/kiosk-self-ordering.md §5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('kiosk_collected_at')->nullable()->after('kiosk_payment_claim_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('kiosk_collected_at');
        });
    }
};
