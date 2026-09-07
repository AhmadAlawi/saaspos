<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tax_registration_number', 64)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('website')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('industry', 32)->nullable();
            $table->json('industries_enabled')->nullable();
            $table->char('base_currency_code', 3);
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(4);
            $table->boolean('tax_registered')->default(true);
            $table->boolean('composition_scheme_enabled')->default(false);
            $table->decimal('composition_rate_percent', 7, 4)->nullable();
            $table->timestamps();

            $table->foreign('base_currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            // Code is unique among LIVE rows only — composite with
            // `deleted_at` so soft-deleted stores don't permanently
            // reserve a code. Index defined at the end of the table.
            $table->string('code', 32);
            $table->string('name');
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('timezone', 64);
            $table->char('currency_code', 3);
            $table->string('locale', 8)->nullable();
            $table->string('rounding_mode', 16)->default('half_up');
            $table->unsignedBigInteger('receipt_template_id')->nullable();
            $table->boolean('enforce_shifts')->default(true);
            $table->boolean('tax_inclusive_pricing')->default(false);
            $table->decimal('cash_variance_tolerance', 15, 4)->default(0);
            $table->decimal('pay_out_threshold', 15, 4)->default(0);
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes()->index();
            $table->unique(['code', 'deleted_at'], 'stores_code_deleted_at_unique');

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        // Now that stores exists, add the FK from users.default_store_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('default_store_id')->references('id')->on('stores')->nullOnDelete();
        });

        Schema::create('store_user', function (Blueprint $table) {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->timestamps();

            $table->primary(['store_id', 'user_id']);
            $table->index('user_id');
            $table->index('role_id');
        });

        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 100);
            $table->json('default_printer_config')->nullable();
            $table->json('label_printer_config')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'code']);
        });

        Schema::create('store_sale_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('period_key', 16)->default('');
            $table->unsignedBigInteger('last_value');

            $table->unique(['store_id', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_sale_counters');
        Schema::dropIfExists('terminals');
        Schema::dropIfExists('store_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_store_id']);
        });
        Schema::dropIfExists('stores');
        Schema::dropIfExists('company');
    }
};
