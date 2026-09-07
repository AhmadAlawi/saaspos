<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->string('app_name', 100)->nullable()->after('name');
            $table->string('app_logo_dark_path')->nullable()->after('app_logo_path');
            $table->string('app_logo_half_path')->nullable()->after('app_logo_dark_path');
            $table->string('app_logo_half_dark_path')->nullable()->after('app_logo_half_path');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn(['app_name', 'app_logo_dark_path', 'app_logo_half_path', 'app_logo_half_dark_path']);
        });
    }
};
