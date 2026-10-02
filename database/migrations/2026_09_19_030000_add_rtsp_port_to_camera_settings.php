<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RTSP is a separate port from the ISAPI HTTP port already stored here
 * (Hikvision default 554) — needed for real live streaming
 * ({@see \App\Services\Cameras\HlsStreamManager}), as opposed to the
 * snapshot/search/download features, which are all plain HTTP ISAPI
 * calls on `port`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camera_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('rtsp_port')->default(554)->after('port');
        });
    }

    public function down(): void
    {
        Schema::table('camera_settings', function (Blueprint $table) {
            $table->dropColumn('rtsp_port');
        });
    }
};
