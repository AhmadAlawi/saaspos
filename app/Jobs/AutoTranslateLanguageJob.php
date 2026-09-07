<?php

namespace App\Jobs;

use App\Actions\Languages\AutoTranslateLanguage;
use App\Models\Language;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs {@see AutoTranslateLanguage} off the request cycle — one network
 * call per untranslated string means a full catalog (hundreds/thousands
 * of keys) takes minutes, far past any HTTP timeout. Triggered from
 * `LanguageController::autoTranslate()`; the admin UI polls the
 * language's progress % (already shown on the Languages index) rather
 * than waiting on this job directly.
 */
class AutoTranslateLanguageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Generous — a large catalog through a free, unofficial, one-string-
     *  at-a-time endpoint is genuinely slow. */
    public int $timeout = 3600;

    public int $tries = 1; // a partial run already committed its progress via chunked upserts — a blind retry would just redo translated-but-slow-flagged rows

    public function __construct(private readonly int $languageId, private readonly bool $overwrite = false) {}

    public function handle(AutoTranslateLanguage $autoTranslate): void
    {
        $language = Language::query()->find($this->languageId);
        if (! $language) {
            return; // deleted before the job ran
        }

        $result = $autoTranslate($language, $this->overwrite);

        Log::info('Auto-translate finished.', [
            'language' => $language->code,
            ...$result,
        ]);

        do_action('language.auto_translate_finished', $language, $result);
    }
}
