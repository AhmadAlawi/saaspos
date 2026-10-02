<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two size additions to the Label Designer:
 *
 *   - `label_layout_elements.height_pct` — every element (name/sku/price/
 *     barcode/image) previously only had `width_pct`; height was implicit
 *     (auto, from font-size/scale). Nullable, same convention as
 *     `width_pct`: null means "use the old implicit sizing," a real value
 *     means the element renders in an explicit box.
 *
 *   - `label_layouts.label_w_mm` / `label_h_mm` — the physical label size
 *     was 100% hardcoded in `config('labels.layouts')` with no per-shop
 *     override. Nullable here too: null means "use the config default for
 *     this layout key," a real value overrides it (see
 *     LabelLayout::effectiveDims()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_layout_elements', function (Blueprint $table) {
            $table->decimal('height_pct', 5, 2)->nullable()->after('width_pct');
        });

        Schema::table('label_layouts', function (Blueprint $table) {
            $table->decimal('label_w_mm', 6, 2)->nullable()->after('layout_key');
            $table->decimal('label_h_mm', 6, 2)->nullable()->after('label_w_mm');
        });
    }

    public function down(): void
    {
        Schema::table('label_layout_elements', function (Blueprint $table) {
            $table->dropColumn('height_pct');
        });

        Schema::table('label_layouts', function (Blueprint $table) {
            $table->dropColumn(['label_w_mm', 'label_h_mm']);
        });
    }
};
