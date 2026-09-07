<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('terminal_id')->nullable()->constrained('terminals')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('opened_at')->useCurrent();
            $table->dateTime('closed_at')->nullable();
            $table->decimal('opening_cash', 15, 4)->default(0);
            $table->json('opening_denominations')->nullable();
            $table->decimal('closing_cash_counted', 15, 4)->nullable();
            $table->json('closing_denominations')->nullable();
            $table->decimal('expected_cash', 15, 4)->nullable();
            $table->decimal('cash_variance', 15, 4)->nullable();
            $table->string('variance_reason')->nullable();
            $table->text('variance_notes')->nullable();
            $table->foreignId('force_closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('handed_off_from_shift_id')->nullable();
            $table->unsignedInteger('sales_count')->default(0);
            $table->decimal('sales_total', 15, 4)->default(0);
            $table->unsignedInteger('refunds_count')->default(0);
            $table->decimal('refunds_total', 15, 4)->default(0);
            $table->json('payment_totals')->nullable();
            $table->string('z_report_path')->nullable();
            $table->string('status', 16)->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('handed_off_from_shift_id')->references('id')->on('shifts')->nullOnDelete();
            $table->index(['store_id', 'status']);
            $table->index(['user_id', 'opened_at']);
        });

        Schema::create('cash_drawer_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->string('type', 16);
            $table->decimal('amount', 15, 4);
            $table->string('reason');
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // FK to expenses added later (expenses migration runs after this one)
            $table->index('expense_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_entries');
        Schema::dropIfExists('shifts');
    }
};
