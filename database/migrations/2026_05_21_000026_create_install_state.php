<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('install_state', function (Blueprint $table) {
            $table->id();
            $table->timestamp('installed_at')->useCurrent();
            $table->string('installer_version', 32);
            $table->string('current_version', 32);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('install_state');
    }
};
