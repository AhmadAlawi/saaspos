<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two fixes for the Shifts slice:
 *
 *  1. `shifts.status` was varchar(16); the `closed_with_variance` value
 *     is 21 chars and was truncated. Widen to varchar(32) so the
 *     literal lands intact.
 *  2. `shift_variance_reasons` table — the close-form dropdown was
 *     hard-coded ENUM-style; making it a real lookup means each
 *     deployment can shape its own taxonomy (theft / miscount / dispute /
 *     local-language variants) and report on it without a code change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->string('status', 32)->default('open')->change();
        });

        Schema::create('shift_variance_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $seeds = [
            ['code' => 'miscount',            'name' => 'Miscount',             'sort_order' => 10],
            ['code' => 'theft_suspected',     'name' => 'Theft suspected',      'sort_order' => 20],
            ['code' => 'change_dispute',      'name' => 'Change dispute',       'sort_order' => 30],
            ['code' => 'unaccounted_pay_out', 'name' => 'Unaccounted pay-out',  'sort_order' => 40],
            ['code' => 'other',               'name' => 'Other',                'sort_order' => 99],
        ];
        foreach ($seeds as $s) {
            DB::table('shift_variance_reasons')->insertOrIgnore(array_merge($s, [
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_variance_reasons');
        Schema::table('shifts', function (Blueprint $table) {
            $table->string('status', 16)->default('open')->change();
        });
    }
};
