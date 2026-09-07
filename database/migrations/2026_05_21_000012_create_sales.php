<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('terminal_id')->nullable()->constrained('terminals')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('number', 32);
            $table->char('local_uuid', 36)->nullable()->unique();
            $table->date('sale_date');
            $table->dateTime('sale_datetime')->useCurrent();
            $table->string('status', 16)->default('draft')->index();
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate_to_base', 20, 10)->default(1);
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount_total', 15, 4)->default(0);
            $table->decimal('tax_total', 15, 4)->default(0);
            $table->decimal('additional_charges_total', 15, 4)->default(0);
            $table->decimal('rounding_adjustment', 15, 4)->default(0);
            $table->decimal('grand_total', 15, 4)->default(0);
            $table->decimal('paid_total', 15, 4)->default(0);
            $table->decimal('change_returned', 15, 4)->default(0);
            $table->decimal('balance_due', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->string('held_label', 64)->nullable();
            $table->foreignId('held_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('held_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->boolean('synced_from_offline')->default(false);
            $table->dateTime('client_completed_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->boolean('is_b2b_invoice')->default(false);
            $table->boolean('requires_einvoice')->default(false);
            $table->string('irn', 64)->nullable();
            $table->text('irn_signed_qr')->nullable();
            $table->timestamp('irn_acknowledged_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['store_id', 'number']);
            $table->unique('irn');
            $table->index(['store_id', 'sale_date']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->string('product_name_snapshot');
            $table->string('sku_snapshot', 64)->nullable();
            $table->string('barcode_snapshot', 64)->nullable();
            $table->decimal('quantity', 15, 4);
            $table->string('unit', 16);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('unit_cost_snapshot', 15, 4)->default(0);
            $table->decimal('discount_percent', 7, 4)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->nullOnDelete();
            $table->json('tax_breakdown')->nullable();
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_subtotal', 15, 4);
            $table->decimal('line_total', 15, 4);
            $table->string('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('quantity_returned', 15, 4)->default(0);

            $table->index(['sale_id', 'sort_order']);
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->decimal('amount', 15, 4);
            $table->decimal('tendered_amount', 15, 4)->nullable();
            $table->decimal('change_returned', 15, 4)->nullable();
            $table->string('reference')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->decimal('amount_in_method_currency', 15, 4)->nullable();
            $table->decimal('exchange_rate_to_sale_currency', 20, 10)->nullable();
            $table->string('cheque_number', 64)->nullable();
            $table->string('cheque_bank')->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('cheque_status', 16)->nullable();
            $table->timestamp('cheque_realized_at')->nullable();
            $table->decimal('cheque_bounce_fee', 15, 4)->nullable();
            $table->string('gateway_provider', 32)->nullable();
            $table->string('gateway_payment_id')->nullable();
            $table->text('gateway_signature')->nullable();
            $table->string('gateway_status', 32)->nullable();
            $table->string('refund_id')->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('type', 16);
            $table->decimal('value', 15, 4);
            $table->decimal('amount', 15, 4);
            $table->string('reason')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('return_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('default_restock')->default(true);
            $table->string('requires_permission', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('linked_exchange_sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->char('client_uuid', 36)->nullable()->unique();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('original_currency_code', 3)->nullable();
            $table->decimal('exchange_rate_to_active', 20, 10)->nullable();
            $table->string('number', 32);
            $table->date('return_date');
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('reason_code_id')->constrained('return_reasons')->restrictOnDelete();
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('tax_total', 15, 4)->default(0);
            $table->decimal('grand_total', 15, 4)->default(0);
            $table->foreignId('refund_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->decimal('refunded_to_store_credit', 15, 4)->nullable();
            $table->decimal('refunded_in_cash', 15, 4)->nullable();
            $table->decimal('refunded_to_original_method', 15, 4)->nullable();
            $table->boolean('restock')->default(true);
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'number']);
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price_snapshot', 15, 4);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4);
            $table->boolean('restock')->nullable();
            $table->string('notes')->nullable();
        });

        // Now link purchase_returns.reason_code_id to return_reasons (shared lookup is fine).
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->foreign('reason_code_id')->references('id')->on('return_reasons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropForeign(['reason_code_id']);
        });
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('return_reasons');
        Schema::dropIfExists('sale_discounts');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
