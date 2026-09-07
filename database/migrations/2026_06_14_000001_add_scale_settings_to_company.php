<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weighing-scale barcode settings (Settings → Scale). A single JSON column
 * mirroring the `cashier_settings` pattern — holds the embedded-weight /
 * embedded-price EAN-13 template the cashier uses to decode scale-printed
 * barcodes into a product + measured weight. Off by default; works with any
 * scale brand because every field of the layout is configurable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->json('scale_settings')->nullable()->after('cashier_settings');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn('scale_settings');
        });
    }
};
