<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS payment sessions — the bridge between a cashier ringing up a
 * sale and a customer choosing which gateway to pay with.
 *
 * Flow per session:
 *   1. Cashier taps "Charge via QR" → server creates a session row
 *      with `status='pending'` and a fresh UUID.
 *   2. Cashier UI renders the QR pointing at `/pay/pos/{uuid}` on
 *      our own domain (NOT the gateway's hosted URL — this is the
 *      whole point of the chooser flow).
 *   3. Customer scans → lands on the chooser page → picks a
 *      gateway (Stripe today; Razorpay/etc. as they're wired). We
 *      stamp `payment_method_id` on the row and redirect the
 *      customer to the gateway-hosted page.
 *   4. Gateway webhook + our return handler mark the row paid +
 *      stash `gateway_payment_id` for the eventual sale_payments
 *      row.
 *   5. The cashier polls `status` every 2s. On `paid` the cashier
 *      auto-completes the sale, baking the session's gateway info
 *      into the payment row.
 *
 * Lifecycle:
 *   pending  → customer hasn't even picked a gateway yet
 *   selected → customer picked, redirected to gateway
 *   paid     → gateway confirmed (webhook OR return URL)
 *   failed   → gateway said no, or generic error
 *   expired  → 15 minutes elapsed without payment
 *   cancelled→ cashier hit "cancel & switch to cash"
 *
 * `local_uuid` ties back to the cashier's cart so the sale-complete
 * flow can correlate which session paid for which ring-up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_payment_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();           // public-facing id in the QR URL
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('local_uuid', 64);               // cart UUID — survives across sale_complete
            $table->decimal('amount', 15, 4);
            $table->char('currency', 3);

            // 32 wide because we learned the hard way that short
            // varchars get truncated on enum-y status strings.
            $table->string('status', 32)->default('pending');

            // Filled when the customer picks a provider.
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();

            // Gateway round-trip identifiers (provider-specific).
            $table->string('gateway_session_id')->nullable();   // e.g. Stripe cs_xxx
            $table->string('gateway_payment_id')->nullable();   // e.g. Stripe pi_xxx

            $table->text('failure_message')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('local_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payment_sessions');
    }
};
