<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `camera_settings` shipped as a single shared-NVR row, then it turned
 * out every branch has its OWN NVR — so this makes it one row per
 * store instead, and folds the channel number in too (it was briefly
 * on `stores.camera_channel`; a channel only means anything alongside
 * the NVR it belongs to, so it belongs on this table, not the store).
 *
 * The one row saved under the old shared-NVR design is left with a
 * null `store_id` (harmless — {@see \App\Models\CameraSettings::forStore()}
 * only ever matches by store_id) rather than guessed onto a store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camera_settings', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->unique()->after('id')->constrained('stores')->nullOnDelete();
            $table->unsignedSmallInteger('channel')->nullable()->after('username');
        });

        if (Schema::hasColumn('stores', 'camera_channel')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('camera_channel');
            });
        }
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedSmallInteger('camera_channel')->nullable();
        });

        Schema::table('camera_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
            $table->dropColumn('channel');
        });
    }
};
