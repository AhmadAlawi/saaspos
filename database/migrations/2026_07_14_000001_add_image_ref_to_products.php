<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `image_ref` to products — the declared image FILENAME a bulk CSV
 * import records for each row (e.g. "apple-front.jpg"), so the separate
 * "Bulk images" screen can match an uploaded file to its product.
 *
 * It is NOT the stored image; that stays in `image_path`. `image_ref` is the
 * lookup key the bulk-image matcher queries (`WHERE image_ref IN (…filenames)`),
 * which is why it's indexed. It survives after the image is attached so a
 * corrected re-upload still matches.
 *
 * Idempotent: re-run safely on already-migrated installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'image_ref')) {
                $table->string('image_ref')->nullable()->after('image_path')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'image_ref')) {
                $table->dropIndex(['image_ref']);
                $table->dropColumn('image_ref');
            }
        });
    }
};
