<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Updater preferences + last-check state (Settings → Updates). These live on
 * the company row — NOT in config/pos.php — so they survive an update, which
 * replaces the config file wholesale.
 *
 *   - channel / auto_check / pinned / skipped : the operator's choices
 *   - last_checked_at + available_* + last_feed_error : the cached result of
 *     the most recent feed check, so the "update available" banner renders
 *     without re-hitting the network on every page load.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->string('update_channel', 16)->default('stable')->after('backup_last_run_at'); // stable | beta
            $table->boolean('update_auto_check')->default(true)->after('update_channel');
            $table->timestamp('update_last_checked_at')->nullable()->after('update_auto_check');
            $table->string('update_available_version', 32)->nullable()->after('update_last_checked_at');
            $table->json('update_available_release')->nullable()->after('update_available_version');
            $table->string('update_pinned_version', 32)->nullable()->after('update_available_release');
            $table->json('update_skipped_versions')->nullable()->after('update_pinned_version');
            $table->text('update_last_feed_error')->nullable()->after('update_skipped_versions');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn([
                'update_channel', 'update_auto_check', 'update_last_checked_at',
                'update_available_version', 'update_available_release',
                'update_pinned_version', 'update_skipped_versions', 'update_last_feed_error',
            ]);
        });
    }
};
