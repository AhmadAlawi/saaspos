<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lookup table for pharmacy drug schedules (Schedule H, OTC, US
 * Schedule II, UK POM, etc.). Lives in the database — not a static
 * enum — so different countries / regulatory regimes can be
 * configured per install without a code change.
 *
 * `code` is the short identifier printed on labels and stored on
 * `products.pharmacy_schedule`. `country_code` lets installs scope
 * the list to a single jurisdiction (null = global / generic).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_schedules');
    }
};
