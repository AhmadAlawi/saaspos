<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the owner dismiss the dashboard "Get your store ready" setup
 * checklist early (the card otherwise auto-hides once every step is
 * complete). Company-wide flag — once any admin dismisses it, the card
 * stays hidden for everyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->boolean('dashboard_setup_dismissed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn('dashboard_setup_dismissed');
        });
    }
};
