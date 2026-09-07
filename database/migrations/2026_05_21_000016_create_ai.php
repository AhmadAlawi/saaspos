<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('feature', 32);
            $table->string('provider', 32);
            $table->string('model', 64);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost_estimate_usd', 15, 6)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('success')->default(true);
            $table->string('error_code', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ai_chat_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->char('session_id', 36);
            $table->string('role', 16);
            $table->text('content');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('session_id');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('ai_reorder_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('current_stock', 15, 4);
            $table->decimal('suggested_quantity', 15, 4);
            $table->decimal('confidence', 5, 2)->nullable();
            $table->text('reasoning')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamp('actioned_at')->nullable();
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_reorder_suggestions');
        Schema::dropIfExists('ai_chat_history');
        Schema::dropIfExists('ai_usage_logs');
    }
};
