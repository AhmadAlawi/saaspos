<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a payment session narrow the gateways its customer-facing chooser
 * (`/pay/pos/{uuid}`) will offer.
 *
 * The cashier's generic QR still advertises every configured gateway
 * (NULL here = no restriction, the existing behaviour). A kiosk session
 * stamps the terminal's `kiosk_config.payment_method_ids` so the shopper
 * only ever sees the providers the merchant enabled for that station —
 * and when exactly one is allowed, the chooser step disappears entirely
 * and the scan lands straight on the gateway.
 *
 * See docs/features/kiosk-self-ordering.md §5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_payment_sessions', function (Blueprint $table) {
            $table->json('allowed_method_ids')->nullable()->after('payment_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('pos_payment_sessions', function (Blueprint $table) {
            $table->dropColumn('allowed_method_ids');
        });
    }
};
