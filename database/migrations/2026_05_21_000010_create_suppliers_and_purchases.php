<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            // Code is unique among LIVE rows only — composite with
            // `deleted_at` so soft-deleted suppliers don't reserve a
            // code (mirrors the customers fix).
            $table->string('code', 32)->nullable();
            $table->string('name');
            $table->string('business_name')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('gstin', 64)->nullable();
            $table->string('tax_registration_number', 64)->nullable();
            $table->string('pan', 16)->nullable();
            $table->char('default_currency_code', 3)->nullable();
            $table->decimal('outstanding_balance', 15, 4)->default(0);
            $table->unsignedInteger('payment_terms_days')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes()->index();

            $table->foreign('default_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['code', 'deleted_at'], 'suppliers_code_deleted_at_unique');
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('number', 32)->unique();
            $table->string('supplier_invoice_number', 64)->nullable();
            $table->date('purchase_date');
            $table->date('due_date')->nullable();
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate_to_base', 20, 10)->default(1);
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount_total', 15, 4)->default(0);
            $table->decimal('tax_total', 15, 4)->default(0);
            $table->decimal('additional_charges_total', 15, 4)->default(0);
            $table->decimal('grand_total', 15, 4)->default(0);
            $table->decimal('paid_total', 15, 4)->default(0);
            $table->decimal('balance_due', 15, 4)->default(0);
            $table->string('status', 16)->default('draft')->index();
            $table->boolean('is_received_in_full')->default(false);
            $table->char('client_uuid', 36)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['store_id', 'created_at']);
            $table->index('supplier_id');
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->string('batch_number', 64)->nullable();
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->decimal('received_quantity', 15, 4)->nullable();
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('additional_charges_share', 15, 4)->nullable();
            $table->decimal('landed_unit_cost', 15, 4)->nullable();
            $table->decimal('discount_percent', 7, 4)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->nullOnDelete();
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4);
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 15, 4);
            $table->string('reference')->nullable();
            // `client_uuid` groups the N rows from one user submission
            // (one allocation per row, shared uuid for a multi-PO pay).
            // NOT unique — that's a per-row idempotency model and breaks
            // multi-allocation payments. Index only, for "find this
            // submission's rows" lookups.
            $table->char('client_uuid', 36)->nullable()->index();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $table->string('number', 32);
            $table->date('return_date');
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('additional_charges', 15, 4)->default(0);
            $table->decimal('grand_total', 15, 4)->default(0);
            $table->decimal('refund_amount', 15, 4)->default(0);
            $table->string('status', 16)->default('draft');
            $table->unsignedBigInteger('reason_code_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'number']);
            $table->index(['store_id', 'return_date']);
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained('purchase_items')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost_snapshot', 15, 4);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4);
            $table->boolean('restock')->default(true);
            $table->string('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
    }
};
