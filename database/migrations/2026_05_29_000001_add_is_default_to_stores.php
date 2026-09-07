<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The company's default store. Exactly one store carries `is_default`.
 * It's the fallback used for price resolution when a product has no
 * store-specific price, and the store new users land on. Enforced as a
 * singleton in App\Actions\Stores\SetDefaultStore.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
        });

        // Backfill: existing installs get their earliest store as default.
        $firstId = DB::table('stores')->orderBy('id')->value('id');
        if ($firstId) {
            DB::table('stores')->where('id', $firstId)->update(['is_default' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
