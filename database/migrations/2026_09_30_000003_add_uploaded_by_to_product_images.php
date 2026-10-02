<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `product_images` already exists as a real, unused polymorphic gallery
 * table (`database/migrations/2026_05_21_000007_create_catalog.php`:
 * `imageable_type`/`imageable_id`/`path`/`sort_order`) — the earlier
 * `2026_09_30_000001_create_product_images` migration collided with it
 * and was a no-op once corrected (guarded on `hasTable()`). The mobile
 * photo-capture tool builds on THIS existing table instead of a new
 * one — only `uploaded_by` needs adding for attribution.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('product_images', 'uploaded_by')) {
            return;
        }

        Schema::table('product_images', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable()->after('sort_order')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by');
        });
    }
};
