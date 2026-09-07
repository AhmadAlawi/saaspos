<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->decimal('default_discount_percent', 7, 4)->nullable();
            $table->unsignedBigInteger('default_price_list_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            // Code is unique among LIVE rows only — composite with
            // `deleted_at` so soft-deleted customers don't permanently
            // reserve a code. Index defined at the end of the table.
            $table->string('code', 32)->nullable();
            $table->string('name')->index();
            $table->boolean('is_business')->default(false);
            $table->string('business_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 32)->nullable()->index();
            $table->string('whatsapp_phone', 32)->nullable();
            $table->boolean('whatsapp_opt_out')->default(false);
            $table->date('dob')->nullable();
            $table->string('gender', 24)->nullable();
            $table->string('gstin', 32)->nullable();
            $table->string('pan', 16)->nullable();
            $table->string('tax_registration_number', 64)->nullable();
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->decimal('default_discount_percent', 7, 4)->nullable();
            $table->decimal('credit_limit', 15, 4)->nullable();
            $table->decimal('outstanding_balance', 15, 4)->default(0);
            $table->decimal('store_credit_balance', 15, 4)->default(0);
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->foreignId('first_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes()->index();
            $table->unique(['code', 'deleted_at'], 'customers_code_deleted_at_unique');
        });

        // Now that customers exists, wire up the FK from tax_exemption_certificates.
        Schema::table('tax_exemption_certificates', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label', 32)->nullable();
            $table->string('line1')->nullable();
            $table->string('line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('landmark')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('type', 32);
            $table->decimal('amount', 15, 4)->nullable();
            $table->integer('points')->nullable();
            $table->decimal('balance_after_amount', 15, 4)->nullable();
            $table->integer('balance_after_points')->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credit_transactions');
        Schema::dropIfExists('customer_addresses');
        Schema::table('tax_exemption_certificates', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        Schema::dropIfExists('customers');
        Schema::dropIfExists('customer_groups');
    }
};
