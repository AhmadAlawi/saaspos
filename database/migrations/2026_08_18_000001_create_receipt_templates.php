<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `receipt_templates` (name, paper_size, template, is_default, is_active)
 * and the FK from `stores.receipt_template_id` **already exist** —
 * shipped by the base product in `2026_05_21_000015_create_receipts.php`,
 * wired at the DB level but never given a model, controller, or UI (0
 * rows on every install, confirmed on staging before writing this). This
 * migration builds our block-based editor on top of that existing table
 * rather than creating a competing one:
 *
 *   - `receipt_template_blocks` is new — the ordered block list a
 *     template is actually made of (logo/text/items/totals/... — see
 *     {@see \App\Models\ReceiptTemplateBlock}).
 *   - the vendor `template` (longText) column is left untouched/unused
 *     by our block-based renderer — new rows store `''` in it.
 *   - `terminals.receipt_template_id` is new (only `stores` had the
 *     column) — the per-terminal override tier in
 *     {@see \App\Actions\Hardware\ResolveReceiptTemplate}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_template_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_template_id')->constrained('receipt_templates')->cascadeOnDelete();
            // logo | store_info | text | items_table | totals | payments |
            // barcode | qr | hsn_summary | divider | spacer | image
            $table->string('type', 24);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            // Free-form per-type payload: {text: "..."} for `text`,
            // {image_path: "..."} for `image`, empty for structural types.
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index(['receipt_template_id', 'sort_order']);
        });

        Schema::table('terminals', function (Blueprint $table) {
            $table->foreignId('receipt_template_id')->nullable()->after('label_printer_config')
                ->constrained('receipt_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->dropForeign(['receipt_template_id']);
            $table->dropColumn('receipt_template_id');
        });

        Schema::dropIfExists('receipt_template_blocks');
    }
};
