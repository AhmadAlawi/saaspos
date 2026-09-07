<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the `brands` and `units` tables in line with how the Categories
 * module treats deletes — soft-deletable so we can refuse a destroy when
 * the row still has products attached, and (for brands) carry the same
 * short description users expect on every catalog entity.
 *
 * Idempotent: re-running on a fresh install OR on an installed copy
 * (where one half has already been seeded) is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (! Schema::hasColumn('brands', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
            if (! Schema::hasColumn('brands', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            }
            if (! Schema::hasColumn('brands', 'deleted_at')) {
                $table->softDeletes()->index();
            }
        });

        Schema::table('units', function (Blueprint $table) {
            if (! Schema::hasColumn('units', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            }
            if (! Schema::hasColumn('units', 'deleted_at')) {
                $table->softDeletes()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (Schema::hasColumn('brands', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('brands', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
            if (Schema::hasColumn('brands', 'description')) {
                $table->dropColumn('description');
            }
        });

        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('units', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};
