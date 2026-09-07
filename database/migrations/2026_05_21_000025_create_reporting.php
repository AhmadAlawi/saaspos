<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('report_key', 64);
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('parameters');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
        });

        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_report_id')->nullable()->constrained('saved_reports')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('report_key', 64);
            $table->string('name');
            $table->json('parameters');
            $table->string('frequency', 16);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->time('time_of_day');
            $table->string('timezone', 64);
            $table->string('format', 8);
            $table->json('recipients_email')->nullable();
            $table->json('recipients_whatsapp')->nullable();
            $table->string('subject_template')->nullable();
            $table->text('message_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scheduled_report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_report_id')->constrained('scheduled_reports')->cascadeOnDelete();
            $table->timestamp('run_at')->useCurrent();
            $table->string('status', 16);
            $table->string('file_path')->nullable();
            $table->json('recipients_delivered')->nullable();
            $table->text('error_message')->nullable();
        });

        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sales_count')->default(0);
            $table->decimal('sales_gross', 15, 4)->default(0);
            $table->decimal('sales_returns', 15, 4)->default(0);
            $table->decimal('sales_net', 15, 4)->default(0);
            $table->decimal('tax_total', 15, 4)->default(0);
            $table->decimal('cogs', 15, 4)->default(0);
            $table->decimal('gross_profit', 15, 4)->default(0);
            $table->decimal('cash_total', 15, 4)->default(0);
            $table->decimal('card_total', 15, 4)->default(0);
            $table->decimal('upi_total', 15, 4)->default(0);
            $table->decimal('gateway_total', 15, 4)->default(0);
            $table->decimal('store_credit_total', 15, 4)->default(0);
            $table->decimal('customer_credit_total', 15, 4)->default(0);
            $table->decimal('discounts_total', 15, 4)->default(0);
            $table->unsignedInteger('transactions_voided')->default(0);
            $table->decimal('avg_basket_size', 15, 4)->default(0);
            $table->decimal('items_sold', 15, 4)->default(0);
            $table->unsignedInteger('new_customers_acquired')->default(0);
            $table->string('refresh_status', 16)->default('current');
            $table->timestamps();

            $table->unique(['store_id', 'date', 'cashier_id']);
            $table->index(['store_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_metrics');
        Schema::dropIfExists('scheduled_report_runs');
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('saved_reports');
    }
};
