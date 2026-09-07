<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in, off by default — a store only gets this behavior when an
 * admin explicitly turns it on. Only meaningful when `enforce_shifts`
 * is also on: it tightens WHEN a shift may open (a trading day must
 * already be open), not whether one is required at all.
 *
 * @see \App\Actions\Shifts\OpenShift (the new enforcement point)
 * @see \App\Actions\Shifts\OpenTradingDay (the explicit "Open Day" action)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('require_day_open')->default(false)->after('enforce_shifts');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('require_day_open');
        });
    }
};
