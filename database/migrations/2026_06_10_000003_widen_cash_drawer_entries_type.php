<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `cash_drawer_entries.type` was varchar(16) but the new
 * `drawer_open_no_sale` enum literal is 19 chars and was truncated on
 * insert. Same fix shape as the shifts.status widening — bump to
 * varchar(32) to give every member of the type ENUM room to land.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_drawer_entries', function (Blueprint $table) {
            $table->string('type', 32)->change();
        });
    }

    public function down(): void
    {
        Schema::table('cash_drawer_entries', function (Blueprint $table) {
            $table->string('type', 16)->change();
        });
    }
};
