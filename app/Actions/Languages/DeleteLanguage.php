<?php

namespace App\Actions\Languages;

use App\Exceptions\LanguageNotDeletable;
use App\Models\Language;
use App\Models\Translation;
use App\Support\Translation\DatabaseMergeLoader;

/**
 * Delete a language and its translation overrides. English (the file
 * reference) and the current default can't be deleted.
 *
 * @throws LanguageNotDeletable
 */
class DeleteLanguage
{
    public function __invoke(Language $language): void
    {
        if ($language->code === 'en') {
            throw new LanguageNotDeletable('base');
        }
        if ($language->is_default) {
            throw new LanguageNotDeletable('default');
        }

        do_action('language.before_delete', $language);

        Translation::query()->where('locale', $language->code)->delete();
        $language->delete();
        DatabaseMergeLoader::bumpVersion();

        do_action('language.after_delete', $language);
    }
}
