<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SaaS entitlement fields cached on the company row, alongside the existing
 * license_* columns (2026_06_15_000001_add_license_settings_to_company.php).
 *
 * Populated by the same PersistLicense action off the license server's
 * `/api/v1/instances/validate` response — plan tier, seat limit, and a
 * feature flag map — so seat/feature enforcement never needs a live network
 * call on the request path.
 *
 * `seats_used_cache` is a display convenience only; enforcement always
 * re-counts live (see Admin\UserController::store()) to avoid a staleness
 * window between phone-home cycles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->string('plan_code', 32)->nullable()->after('license_last_error');
            $table->unsignedInteger('seat_limit')->nullable()->after('plan_code');
            $table->unsignedInteger('seats_used_cache')->nullable()->after('seat_limit');
            $table->json('features')->nullable()->after('seats_used_cache');
            $table->timestamp('license_grace_until')->nullable()->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn([
                'plan_code', 'seat_limit', 'seats_used_cache', 'features', 'license_grace_until',
            ]);
        });
    }
};
