<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Category-level tax. At checkout the tax resolver picks the category's
 * tax_group_id first; only when null does it fall back to the product's
 * own tax. Nullable here so existing rows keep using product-level tax.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('categories', 'tax_group_id')) {
            return; // already present from a prior run
        }
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('tax_group_id')->nullable()->after('color')
                ->constrained('tax_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('categories', 'tax_group_id')) {
            return;
        }
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_group_id');
        });
    }
};
