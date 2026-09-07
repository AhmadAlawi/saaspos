<?php

namespace App\Actions\Languages;

use App\Models\Language;

/**
 * Add a language. New languages start with no translations — every key
 * falls back to English until the owner imports a translated file.
 */
class CreateLanguage
{
    /** @param array<string, mixed> $data */
    public function __invoke(array $data): Language
    {
        do_action('language.before_create', $data);

        $language = Language::create([
            'code'        => $data['code'],
            'name'        => $data['name'],
            'native_name' => $data['native_name'] ?: $data['name'],
            'direction'   => $data['direction'] ?? 'ltr',
            'is_active'   => $data['is_active'] ?? true,
            'is_default'  => false,
            'sort_order'  => Language::max('sort_order') + 1,
        ]);

        do_action('language.after_create', $language);

        return $language;
    }
}
