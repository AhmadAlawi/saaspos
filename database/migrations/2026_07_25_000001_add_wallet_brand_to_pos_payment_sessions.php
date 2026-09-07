<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 'apple_pay' | 'google_pay' | null — set only when the cashier tapped a
 * wallet button. Tells CustomerPayController::show() to render the
 * minimal Payment Request Button page (pay.pos_wallet) instead of the
 * generic gateway chooser / Checkout Session redirect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_payment_sessions', function (Blueprint $table) {
            $table->string('wallet_brand', 32)->nullable()->after('gateway_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('pos_payment_sessions', function (Blueprint $table) {
            $table->dropColumn('wallet_brand');
        });
    }
};
