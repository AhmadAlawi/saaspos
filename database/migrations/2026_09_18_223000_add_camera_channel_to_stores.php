<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which Hikvision NVR channel covers this store's till — set on the
 * store since (for now, beta) one shared company NVR serves every store's
 * cameras and a store maps to exactly one channel. See
 * {@see \App\Http\Controllers\Admin\SaleCameraController}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedSmallInteger('camera_channel')->nullable()->after('receipt_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('camera_channel');
        });
    }
};
