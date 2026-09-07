<?php

namespace App\Actions\Languages;

use App\Models\Language;

/**
 * Update a language's metadata. The `code` of the base language (en) is
 * immutable to keep it as the stable file reference.
 */
class UpdateLanguage
{
    /** @param array<string, mixed> $data */
    public function __invoke(Language $language, array $data): Language
    {
        do_action('language.before_update', $language, $data);

        $language->update([
            'code'        => $language->code === 'en' ? 'en' : $data['code'],
            'name'        => $data['name'],
            'native_name' => $data['native_name'] ?: $data['name'],
            'direction'   => $data['direction'] ?? $language->direction,
            'is_active'   => $data['is_active'] ?? $language->is_active,
        ]);

        do_action('language.after_update', $language);

        return $language;
    }
}
