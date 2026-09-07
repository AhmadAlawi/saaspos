<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the base language set. English is the default (and the file
 * reference). Arabic + Spanish ship active so the language switcher and
 * RTL are demonstrable immediately — untranslated strings fall back to
 * English until the owner translates them via Settings → Languages.
 *
 * Idempotent: matches by `code`.
 */
class LanguagesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $languages = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English',  'direction' => 'ltr', 'is_default' => true,  'sort_order' => 0],
            ['code' => 'ar', 'name' => 'Arabic',  'native_name' => 'العربية',  'direction' => 'rtl', 'is_default' => false, 'sort_order' => 1],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español',  'direction' => 'ltr', 'is_default' => false, 'sort_order' => 2],
        ];

        foreach ($languages as $lang) {
            DB::table('languages')->updateOrInsert(
                ['code' => $lang['code']],
                array_merge($lang, ['is_active' => true, 'updated_at' => $now, 'created_at' => $now]),
            );
        }

        // Guarantee exactly one default.
        if (! DB::table('languages')->where('is_default', true)->exists()) {
            DB::table('languages')->where('code', 'en')->update(['is_default' => true]);
        }
    }
}
