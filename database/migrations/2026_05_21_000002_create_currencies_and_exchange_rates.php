<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->string('name', 100);
            $table->string('symbol', 8);
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->boolean('symbol_first')->default(true);
            $table->string('thousands_separator', 2)->default(',');
            $table->string('decimal_separator', 2)->default('.');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('from_currency', 3);
            $table->char('to_currency', 3);
            $table->decimal('rate', 20, 10);
            $table->date('effective_from');
            $table->string('source', 32)->default('manual');
            $table->timestamps();

            $table->foreign('from_currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('to_currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['from_currency', 'to_currency', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
