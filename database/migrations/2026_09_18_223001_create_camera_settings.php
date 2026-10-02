<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row connection config for the company's shared Hikvision NVR
 * (ISAPI). One NVR serves every store's cameras; a store picks its own
 * channel via `stores.camera_channel`. See
 * {@see \App\Services\Cameras\HikvisionClient}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camera_settings', function (Blueprint $table) {
            $table->id();
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->default(80);
            $table->boolean('use_https')->default(false);
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camera_settings');
    }
};
