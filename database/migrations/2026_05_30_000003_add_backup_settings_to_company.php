<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backup settings (Settings → Backup): whether automatic backups run,
 * how often, where they go, and how long to keep them. The scheduler
 * (routes/console.php) reads these to decide when to invoke the
 * `RunBackup` action. Backups are PHP-based SQL dumps + an optional
 * uploads zip — no `mysqldump` so it works on shared hosting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->boolean('backup_enabled')->default(false)->after('mail_from_name');
            $table->string('backup_frequency', 16)->default('weekly')->after('backup_enabled'); // daily | weekly | monthly
            $table->unsignedSmallInteger('backup_retention_days')->default(30)->after('backup_frequency');
            $table->string('backup_target_disk', 32)->default('local')->after('backup_retention_days');
            $table->boolean('backup_include_uploads')->default(true)->after('backup_target_disk');
            $table->timestamp('backup_last_run_at')->nullable()->after('backup_include_uploads');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn([
                'backup_enabled', 'backup_frequency', 'backup_retention_days',
                'backup_target_disk', 'backup_include_uploads', 'backup_last_run_at',
            ]);
        });
    }
};
