<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Branding text color (Settings → Branding): the foreground color used on
 * top of accent-filled surfaces — primary buttons, the launch button, the
 * active rank pill. Maps to the `--accent-fg` token. Null = white (the
 * token default), which suits most accents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->string('brand_text_color', 7)->nullable()->after('brand_color');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn('brand_text_color');
        });
    }
};
