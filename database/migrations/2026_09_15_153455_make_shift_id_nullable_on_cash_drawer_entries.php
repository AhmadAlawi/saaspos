<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A drawer-open-no-sale entry can now happen with NO shift running on
 * the terminal (e.g. a manager popping the drawer before anyone's
 * clocked in) — `shift_id` NULL means exactly that: this entry belongs
 * to no shift and is never picked up by any Z-report's
 * `ComputeShiftTotals` (which filters `where('shift_id', $shiftId)`).
 * `pay_in`/`pay_out` still always carry a real shift — only
 * `drawer_open_no_sale` is ever recorded shift-less, enforced at the
 * application layer (RecordCashDrawerEntry), not by this column alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_drawer_entries', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
        });

        Schema::table('cash_drawer_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('shift_id')->nullable()->change();
        });

        Schema::table('cash_drawer_entries', function (Blueprint $table) {
            $table->foreign('shift_id')->references('id')->on('shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_drawer_entries', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
        });

        Schema::table('cash_drawer_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('shift_id')->nullable(false)->change();
        });

        Schema::table('cash_drawer_entries', function (Blueprint $table) {
            $table->foreign('shift_id')->references('id')->on('shifts')->cascadeOnDelete();
        });
    }
};
