<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name', 100);
            $table->string('type', 16);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('account_groups')->nullOnDelete();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_group_id')->constrained('account_groups')->restrictOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('type', 16);
            $table->char('currency_code', 3);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 32);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->string('name', 32);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_locked')->default(false);
        });

        Schema::create('account_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64);
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['key', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('account_groups');
    }
};
