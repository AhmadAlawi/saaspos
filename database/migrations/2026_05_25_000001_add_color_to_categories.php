<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a per-category accent color. Stored as a 7-char hex string
 * (e.g. "#F97316"). Nullable so legacy rows default to a neutral
 * tile via CSS fallback.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('categories', 'color')) {
            return; // already present from a prior run
        }
        Schema::table('categories', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('categories', 'color')) {
            return;
        }
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
