<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('product_store_prices', 'mrp')) {
            return;
        }

        Schema::table('product_store_prices', function (Blueprint $table) {
            $table->decimal('mrp', 15, 4)->nullable()->after('selling_price');
        });
    }

    public function down(): void
    {
        Schema::table('product_store_prices', function (Blueprint $table) {
            $table->dropColumn('mrp');
        });
    }
};
