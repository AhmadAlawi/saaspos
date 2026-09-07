<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-Ordering Kiosk — Slice 1.
 *
 * A terminal can act as a normal staff till (`type = register`, the default)
 * or a customer-operated self-ordering kiosk (`type = kiosk`). Kiosk-specific
 * config (mode, welcome/thank-you copy, idle timeout) rides in `kiosk_config`
 * JSON alongside the hardware + CFD config on the same `terminals` row, so a
 * station is configured in one place. See docs/features/kiosk-self-ordering.md §7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            // register | kiosk. Kept as a short string (not a DB enum) so the
            // v1.1 restaurant flavours can add values without a schema change.
            $table->string('type', 16)->default('register')->after('code');
            $table->json('kiosk_config')->nullable()->after('cfd_config');
        });
    }

    public function down(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->dropColumn(['type', 'kiosk_config']);
        });
    }
};
