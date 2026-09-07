<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Branding / theme settings on the single company row (Settings →
 * Branding). The accent color overrides the `--accent` CSS variables at
 * render; favicon + default theme are injected into the layout head.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->string('brand_color', 9)->nullable()->after('logo_path');     // #RRGGBB
            $table->string('favicon_path')->nullable()->after('brand_color');
            $table->string('theme_default', 8)->default('light')->after('favicon_path'); // light|dark|auto
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn(['brand_color', 'favicon_path', 'theme_default']);
        });
    }
};
