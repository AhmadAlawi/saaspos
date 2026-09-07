<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `is_default` to categories + brands. Exactly one row of each
 * carries the flag and is non-deletable — it's the fallback target
 * when a user deletes another category/brand that still has products
 * linked. New installs get "Uncategorized" / "Generic" seeded; existing
 * installs get the row created here so the rest of the delete-with-move
 * flow always has something to fall back to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
        });

        $now = now();

        // Categories: seed "Uncategorized" if no row currently flagged.
        $defaultCategoryId = DB::table('categories')->where('is_default', true)->value('id');
        if (! $defaultCategoryId) {
            $existing = DB::table('categories')->whereRaw('LOWER(name) = ?', ['uncategorized'])->first();
            if ($existing) {
                DB::table('categories')->where('id', $existing->id)->update([
                    'is_default' => true,
                    'is_active'  => true,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('categories')->insert([
                    'name'       => 'Uncategorized',
                    'slug'       => 'uncategorized',
                    'is_active'  => true,
                    'is_default' => true,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Brands: seed "Generic" if no row currently flagged.
        $defaultBrandId = DB::table('brands')->where('is_default', true)->value('id');
        if (! $defaultBrandId) {
            $existing = DB::table('brands')->whereRaw('LOWER(name) = ?', ['generic'])->first();
            if ($existing) {
                DB::table('brands')->where('id', $existing->id)->update([
                    'is_default' => true,
                    'is_active'  => true,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('brands')->insert([
                    'name'       => 'Generic',
                    'slug'       => 'generic',
                    'is_active'  => true,
                    'is_default' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
