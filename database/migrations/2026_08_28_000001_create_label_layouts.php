<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The web "Label Designer" — free positioning for the product label sheet
 * (`admin/products/labels`), mirroring the mobile app's per-label-spec
 * Label Layout Designer. Unlike the receipt canvas
 * (`receipt_template_elements`), the element set here is fixed (name, sku,
 * price, barcode, an optional logo image) — nothing to add or remove, only
 * position/font/visibility to edit. One row per `config('labels.layouts')`
 * key; a layout that's never been opened in the designer has no row at
 * all, so {@see \App\Http\Controllers\Admin\LabelController::sheet()}
 * keeps rendering its existing fixed-stack markup byte-for-byte unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('layout_key', 32)->unique();
            $table->timestamps();
        });

        Schema::create('label_layout_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_layout_id')->constrained('label_layouts')->cascadeOnDelete();
            // name | sku | price | barcode | image
            $table->string('type', 16);
            // Percent of the label box, top-left origin — not mm, so one
            // designer UI works unmodified across every layout size.
            $table->decimal('x_pct', 5, 2)->default(0);
            $table->decimal('y_pct', 5, 2)->default(0);
            $table->decimal('width_pct', 5, 2)->nullable();
            // pt — name/sku/price only.
            $table->unsignedTinyInteger('font_size')->nullable();
            // One of LabelLayoutElement::FONTS — name/sku/price only.
            $table->string('font_family', 8)->nullable();
            $table->string('align', 8)->default('center');
            // Size multiplier — barcode/image only.
            $table->decimal('scale', 3, 2)->default(1.00);
            $table->boolean('is_visible')->default(true);
            // {image_path: "..."} for the `image` type only.
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['label_layout_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_layout_elements');
        Schema::dropIfExists('label_layouts');
    }
};
