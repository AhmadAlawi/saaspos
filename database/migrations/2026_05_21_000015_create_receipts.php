<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('paper_size', 16);
            $table->longText('template');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('receipt_public_links', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 100);
            $table->unsignedBigInteger('reference_id');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedBigInteger('rotated_from_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('rotated_from_id')->references('id')->on('receipt_public_links')->nullOnDelete();
            $table->index(['reference_type', 'reference_id']);
        });

        // Wire up the deferred FK from stores.receipt_template_id
        Schema::table('stores', function (Blueprint $table) {
            $table->foreign('receipt_template_id')->references('id')->on('receipt_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['receipt_template_id']);
        });
        Schema::dropIfExists('receipt_public_links');
        Schema::dropIfExists('receipt_templates');
    }
};
