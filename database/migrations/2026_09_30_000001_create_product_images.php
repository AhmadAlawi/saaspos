<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product's captured photo gallery — additive, separate from
 * `products.image_path` (the single admin-controlled catalog image,
 * which this never touches). Built for the mobile floor-capture tool:
 * scan a barcode, take a photo, repeat — each photo is its own row.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded: a prior deploy's release step was killed mid-migrate
        // (after this table's DDL committed but before Laravel wrote the
        // migration-log row), which left every later boot re-attempting
        // `CREATE TABLE` and crash-looping. Safe to skip when it's
        // already there with the right shape.
        if (Schema::hasTable('product_images')) {
            return;
        }

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
