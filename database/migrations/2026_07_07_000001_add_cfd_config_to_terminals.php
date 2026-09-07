<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer-Facing Display (CFD) — Slice 1.
 *
 * Per-terminal config for the second screen turned toward the shopper:
 * whether it's enabled, plus optional welcome / thank-you copy. Lives
 * alongside the hardware config on the same `terminals` row so a station's
 * peripherals + customer display are configured in one place.
 * See docs/features/customer-display.md §6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->json('cfd_config')->nullable()->after('label_printer_config');
        });
    }

    public function down(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->dropColumn('cfd_config');
        });
    }
};
