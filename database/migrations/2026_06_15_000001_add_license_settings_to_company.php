<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * License state cached on the company row (docs/features/installer.md §3 + §6).
 *
 * The license is validated at install Step 3 — BEFORE the DB exists (it's set
 * up at Step 4) — so the validated response is stashed in the installer state
 * file and written here at completion. The license KEY itself stays in .env
 * (LICENSE_KEY); these columns hold the human-facing details + the cached
 * result of the most recent re-check, so the admin License page and any
 * "couldn't verify" banner render without hitting the network on every load.
 *
 * A failed re-check NEVER locks the app — it only updates `license_last_error`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->string('license_status', 16)->nullable()->after('update_last_feed_error'); // valid | invalid | unverified
            $table->string('license_type', 32)->nullable()->after('license_status');           // regular | extended
            $table->string('license_buyer_name')->nullable()->after('license_type');
            $table->string('license_buyer_email')->nullable()->after('license_buyer_name');
            $table->timestamp('license_expires_at')->nullable()->after('license_buyer_email');
            $table->timestamp('license_support_until')->nullable()->after('license_expires_at');
            $table->timestamp('license_last_checked_at')->nullable()->after('license_support_until');
            $table->text('license_last_error')->nullable()->after('license_last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn([
                'license_status', 'license_type', 'license_buyer_name', 'license_buyer_email',
                'license_expires_at', 'license_support_until', 'license_last_checked_at', 'license_last_error',
            ]);
        });
    }
};
