<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records that a kiosk shopper says they paid a `placed` order by scanning a
 * static QR (UPI) at the machine.
 *
 * A static `upi://pay?…` QR has no webhook and no return redirect — nothing
 * tells the server the transfer happened. So the kiosk must NOT complete the
 * sale on the customer's word. Instead the order is placed like any other
 * kiosk order and stamped with the method the customer claims to have used;
 * staff see the claim in the orders queue, check their own UPI app, and
 * settle it there (the queue already renders a UPI QR + UTR reference field).
 *
 * NULL = no claim, i.e. an ordinary pay-at-counter kiosk order.
 *
 * See docs/features/kiosk-self-ordering.md §5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('kiosk_payment_claim_method_id')
                ->nullable()
                ->after('kiosk_note')
                ->constrained('payment_methods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kiosk_payment_claim_method_id');
        });
    }
};
