<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apple Wallet loyalty-card credentials. The Pass Type ID CERTIFICATE
 * itself is a file, stored on the `local` disk (never `public` — it's
 * a real signing credential), not a DB column — see
 * AppleWalletSettingsController. Only the cert's PASSWORD, team ID, and
 * pass-type identifier live here, mirroring CameraSettings' encrypted-
 * credential pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->boolean('apple_wallet_enabled')->default(false)->after('loyalty_redeem_rate');
            $table->string('apple_team_id')->nullable()->after('apple_wallet_enabled');
            $table->string('apple_pass_type_id')->nullable()->after('apple_team_id');
            $table->text('apple_cert_password')->nullable()->after('apple_pass_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn(['apple_wallet_enabled', 'apple_team_id', 'apple_pass_type_id', 'apple_cert_password']);
        });
    }
};
