<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->string('type', 16);
            $table->string('provider', 32)->default('none');
            $table->string('icon', 64)->nullable();
            $table->string('color', 16)->nullable();
            $table->json('provider_credentials')->nullable();
            $table->boolean('requires_reference')->default(false);
            $table->boolean('opens_cash_drawer')->default(false);
            $table->foreignId('accounting_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payment_method_store', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->constrained('payment_methods')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->boolean('is_enabled')->nullable();
            $table->string('display_name_override', 100)->nullable();
            $table->boolean('requires_reference_override')->nullable();
            $table->foreignId('accounting_account_override_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->unsignedInteger('sort_order_override')->nullable();
            $table->json('provider_credentials')->nullable();
            $table->timestamps();

            $table->primary(['payment_method_id', 'store_id']);
        });

        Schema::create('payment_webhook_log', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_type', 64);
            $table->string('gateway_event_id')->nullable();
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->string('result', 32)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->unique(['provider', 'gateway_event_id']);
            $table->index(['processed', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_log');
        Schema::dropIfExists('payment_method_store');
        Schema::dropIfExists('payment_methods');
    }
};
