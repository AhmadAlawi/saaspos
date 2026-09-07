<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track the gateway-side reversal for refunds sent back to the original
 * payment method. When a card/Stripe/Razorpay sale is refunded, we now call
 * the gateway's refund API to actually reverse the charge; these columns
 * record whether that reversal succeeded so failures can be retried and
 * surfaced (see App\Actions\Sales\ReverseGatewayCharge).
 *
 * `gateway_refund_status`:
 *   null        — no gateway reversal applies (cash / store-credit / a
 *                 non-gateway original method)
 *   'succeeded' — the gateway confirmed the reversal
 *   'failed'    — the gateway call errored; eligible for retry
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->string('gateway_refund_status', 16)->nullable()->after('refunded_to_original_method');
            $table->unsignedTinyInteger('gateway_refund_attempts')->default(0)->after('gateway_refund_status');
            $table->text('gateway_refund_error')->nullable()->after('gateway_refund_attempts');
            $table->timestamp('gateway_refunded_at')->nullable()->after('gateway_refund_error');
        });
    }

    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn([
                'gateway_refund_status',
                'gateway_refund_attempts',
                'gateway_refund_error',
                'gateway_refunded_at',
            ]);
        });
    }
};
