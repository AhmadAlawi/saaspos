<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled, time-boxed discounts — "20% off all Shoes, Aug 10-12" — as
 * opposed to the manual per-sale/per-line discount a cashier applies at
 * checkout ({@see \App\Models\SaleDiscount}). A rule targets everything
 * (`scope=all`), one category (`scope=category`), or one product
 * (`scope=product`); `store_id` further narrows it to a single store,
 * or applies everywhere when null.
 *
 * Consumed by {@see \App\Actions\Products\ResolveProductPrice}, which is
 * the single place "what does this product actually cost right now"
 * gets decided for both cashier checkout and kiosk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scope', 16); // all | category | product
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->string('discount_type', 8); // pct | amt
            $table->decimal('discount_value', 15, 4);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'category_id']);
            $table->index(['scope', 'product_id']);
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_rules');
    }
};
