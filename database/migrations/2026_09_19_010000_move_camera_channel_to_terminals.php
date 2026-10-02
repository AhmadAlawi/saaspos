<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A store's NVR can have several cameras covering several terminals —
 * one channel per STORE was wrong the moment a store has more than one
 * till. The camera-settings edit page now lists every channel the NVR
 * reports (live thumbnails) and assigns one to each terminal directly,
 * so the channel number belongs on `terminals`, not `camera_settings`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->unsignedSmallInteger('camera_channel')->nullable()->after('id');
        });

        if (Schema::hasColumn('camera_settings', 'channel')) {
            Schema::table('camera_settings', function (Blueprint $table) {
                $table->dropColumn('channel');
            });
        }
    }

    public function down(): void
    {
        Schema::table('camera_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('channel')->nullable();
        });

        Schema::table('terminals', function (Blueprint $table) {
            $table->dropColumn('camera_channel');
        });
    }
};
