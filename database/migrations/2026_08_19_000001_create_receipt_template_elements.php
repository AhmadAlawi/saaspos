<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a "Canvas" layout mode alongside the existing block-based editor
 * (`receipt_template_blocks`, shipped earlier this session) — free x/y
 * positioning instead of list order, needed because ESC/POS text mode
 * has no native concept of pixel positions (see
 * {@see \App\Services\Hardware\CanvasReceiptRasterizer}, which rasterizes
 * the whole receipt as one bitmap for this mode). Purely additive:
 * `layout_mode` defaults to `blocks`, so every existing template keeps
 * using the block renderer exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_templates', function (Blueprint $table) {
            $table->string('layout_mode', 16)->default('blocks')->after('paper_size');
        });

        Schema::create('receipt_template_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_template_id')->constrained('receipt_templates')->cascadeOnDelete();
            // text | image | items_table | totals | payments | barcode | qr |
            // logo | field.date | field.sale_number | field.customer_name |
            // field.cashier_name | field.store_name | field.store_address |
            // field.grand_total
            $table->string('type', 32);
            // Millimeters, top-left origin. Canonical unit — converted to
            // CSS mm units for HTML and to dots (203dpi assumed) for ESC/POS.
            $table->decimal('x', 8, 2)->default(0);
            $table->decimal('y', 8, 2)->default(0);
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->integer('z_index')->default(0);
            $table->unsignedSmallInteger('font_size')->default(10);
            $table->string('align', 8)->default('left');
            $table->boolean('is_bold')->default(false);
            $table->boolean('is_visible')->default(true);
            // {text: "..."} | {image_path: "..."} | {columns: [...], show_discount_subline: bool, show_batch_subline: bool} for items_table
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index(['receipt_template_id', 'z_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_template_elements');

        Schema::table('receipt_templates', function (Blueprint $table) {
            $table->dropColumn('layout_mode');
        });
    }
};
