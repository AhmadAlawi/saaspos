<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->decimal('rate', 7, 4);
            $table->foreignId('accounting_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('accounting_input_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_recoverable')->default(false);
            $table->boolean('is_reverse_chargeable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->string('logical_code', 32)->nullable();
            $table->json('applies_when')->nullable();
            $table->string('classification', 32)->default('taxable');
            $table->boolean('is_reverse_charge')->default(false);
            $table->boolean('is_inclusive')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_group_components', function (Blueprint $table) {
            $table->foreignId('tax_group_id')->constrained('tax_groups')->cascadeOnDelete();
            $table->foreignId('tax_component_id')->constrained('tax_components')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            $table->primary(['tax_group_id', 'tax_component_id']);
        });

        Schema::create('tax_exemptions', function (Blueprint $table) {
            $table->id();
            $table->string('exemptable_type', 100);
            $table->unsignedBigInteger('exemptable_id');
            $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['exemptable_type', 'exemptable_id']);
        });

        Schema::create('tax_exemption_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('certificate_number', 64);
            $table->string('jurisdiction', 64)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable()->index();
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('customer_id');
            // FK to customers added later in customers migration to avoid circular ordering.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_exemption_certificates');
        Schema::dropIfExists('tax_exemptions');
        Schema::dropIfExists('tax_group_components');
        Schema::dropIfExists('tax_groups');
        Schema::dropIfExists('tax_components');
    }
};
