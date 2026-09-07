<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-language support (docs/features/multi-language.md).
 *
 *  - `languages`     : the locales the install offers (one default, RTL-aware).
 *  - `translations`  : per-locale string overrides layered over the
 *                      `lang/*.php` file defaults by DatabaseMergeLoader.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();      // en, ar, pt-BR
            $table->string('name', 64);                // English name
            $table->string('native_name', 64);         // endonym
            $table->string('direction', 3)->default('ltr'); // ltr | rtl
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 12)->index();
            $table->string('group', 64);               // lang file name, or '*' for JSON
            $table->string('key');                     // dot-path within the group
            $table->text('value');
            $table->timestamps();

            $table->unique(['locale', 'group', 'key'], 'translations_locale_group_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
        Schema::dropIfExists('languages');
    }
};
