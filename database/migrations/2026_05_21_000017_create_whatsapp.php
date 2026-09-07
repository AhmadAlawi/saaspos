<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('mode', 16);
            $table->string('recipient_phone', 32);
            $table->string('reference_type', 100);
            $table->unsignedBigInteger('reference_id');
            $table->string('message_template', 64)->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 16)->default('prepared');
            $table->string('meta_message_id')->nullable();
            $table->unsignedInteger('cloud_api_cost_cents')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('whatsapp_message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->string('locale', 8);
            $table->text('body');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['store_id', 'document_type', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_templates');
        Schema::dropIfExists('whatsapp_logs');
    }
};
