<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in: email a copy of each backup to the company email after it completes.
 * Off by default — a database dump is sensitive and SMTP attachment limits
 * apply, so the operator turns it on deliberately (Settings → Backup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->boolean('backup_email_enabled')->default(false)->after('backup_include_uploads');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn('backup_email_enabled');
        });
    }
};
