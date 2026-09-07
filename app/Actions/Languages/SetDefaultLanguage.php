<?php

namespace App\Actions\Languages;

use App\Models\Language;
use Illuminate\Support\Facades\DB;

/**
 * Make a language the single default (the fallback locale + new-user
 * default). Clears the flag on every other language and forces the new
 * default active. Mirrors the default-store singleton pattern.
 */
class SetDefaultLanguage
{
    public function __invoke(Language $language): Language
    {
        DB::transaction(function () use ($language) {
            Language::query()
                ->where('is_default', true)
                ->whereKeyNot($language->getKey())
                ->update(['is_default' => false]);

            $language->forceFill(['is_default' => true, 'is_active' => true])->save();
        });

        do_action('language.default_changed', $language);

        return $language;
    }
}
