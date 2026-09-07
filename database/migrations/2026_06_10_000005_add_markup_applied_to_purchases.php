<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persists the per-PO "Update selling prices using markup?" decision
 * made on the receive button.
 *
 *   - null  → not asked / not eligible (no line had a markup_percent
 *             set, or the receive happened before this column existed).
 *   - true  → buyer (or company-default) said yes; selling prices
 *             were recomputed for any markup-managed SKU on this PO.
 *   - false → buyer (or company-default) said no; prices stayed put.
 *
 * Kept separate from the existing `is_received_in_full` flag because
 * the two answer different questions and may diverge once we add
 * per-line short-receive in v1.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->boolean('markup_applied')->nullable()->after('is_received_in_full');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('markup_applied');
        });
    }
};
