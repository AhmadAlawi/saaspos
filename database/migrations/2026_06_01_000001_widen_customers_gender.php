<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen `customers.gender` from VARCHAR(16) to VARCHAR(24).
 *
 * The original column was sized for short tokens but the allowed value
 * `prefer_not_to_say` is 17 characters and overflows, raising
 * `SQLSTATE[22001]` on insert. 24 gives us headroom for any future
 * additions (e.g. `non_binary`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('gender', 24)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('gender', 16)->nullable()->change();
        });
    }
};
