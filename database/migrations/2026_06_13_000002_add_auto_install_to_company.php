<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-install preference (Settings → Updates). Off by default. When on, the
 * daily check auto-installs eligible PATCH-level releases (never minor/major),
 * each with the usual pre-update backup + rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->boolean('update_auto_install')->default(false)->after('update_auto_check');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn('update_auto_install');
        });
    }
};
