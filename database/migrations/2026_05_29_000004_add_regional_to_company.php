<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regional settings on the company row (Settings → Regional): the
 * system-wide time zone + date/time display formats. Localization is
 * system-wide (not per-store), so stores inherit this time zone.
 * Timestamps are stored UTC and displayed in this zone via format_date()
 * / format_datetime().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('country_code');
            $table->string('date_format', 32)->default('d M Y')->after('timezone');
            $table->string('time_format', 16)->default('h:i A')->after('date_format');
        });

        // Backfill the company time zone from the default store (if any),
        // so existing installs keep their configured zone.
        $tz = DB::table('stores')->whereNotNull('timezone')->orderByDesc('is_default')->value('timezone');
        if ($tz) {
            DB::table('company')->update(['timezone' => $tz]);
        }
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'date_format', 'time_format']);
        });
    }
};
