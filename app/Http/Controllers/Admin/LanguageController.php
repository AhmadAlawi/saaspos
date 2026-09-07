<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Languages\CreateLanguage;
use App\Actions\Languages\DeleteLanguage;
use App\Actions\Languages\ExportTranslations;
use App\Actions\Languages\ImportTranslations;
use App\Actions\Languages\SetDefaultLanguage;
use App\Actions\Languages\UpdateLanguage;
use App\Exceptions\LanguageNotDeletable;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LanguageRequest;
use App\Jobs\AutoTranslateLanguageJob;
use App\Models\Language;
use App\Services\Translation\TranslationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Languages admin (Settings → Languages). View/export gated by
 * `settings.view`; create/edit/import/default/activate/delete by
 * `settings.update`. See docs/features/multi-language.md §5.
 */
class LanguageController extends Controller
{
    use RespondsJsonOrRedirect;

    public function index(TranslationCatalog $catalog): View
    {
        $this->authorize('settings.view');

        $languages = Language::query()->ordered()->get()->map(function (Language $l) use ($catalog) {
            $l->progress = $catalog->progress($l->code);
            return $l;
        });

        return view('admin.languages.index', [
            'languages'  => $languages,
            'totalKeys'  => $catalog->totalKeys(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('settings.update');

        return view('admin.languages.create');
    }

    public function store(LanguageRequest $request, CreateLanguage $create): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        $language = ($create)($request->languageAttributes());

        return $this->jsonOrRedirect(
            $request,
            __('languages.flash.created', ['name' => $language->name]),
            route('admin.languages.index'),
        );
    }

    public function edit(Language $language): View
    {
        $this->authorize('settings.update');

        return view('admin.languages.edit', ['language' => $language]);
    }

    public function update(LanguageRequest $request, Language $language, UpdateLanguage $update): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        ($update)($language, $request->languageAttributes());

        return $this->jsonOrRedirect(
            $request,
            __('languages.flash.updated', ['name' => $language->name]),
            route('admin.languages.index'),
        );
    }

    public function setDefault(Request $request, Language $language, SetDefaultLanguage $setDefault): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        ($setDefault)($language);

        return $this->jsonOrRedirect(
            $request,
            __('languages.flash.default_set', ['name' => $language->name]),
            route('admin.languages.index'),
        );
    }

    public function toggle(Request $request, Language $language): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        // Keep the default language always active.
        if (! $data['is_active'] && $language->is_default) {
            return $this->jsonOrError(
                $request,
                __('languages.errors.default_active', ['name' => $language->name]),
                route('admin.languages.index'),
            );
        }

        $language->forceFill(['is_active' => (bool) $data['is_active']])->save();

        $key = $data['is_active'] ? 'languages.flash.activated' : 'languages.flash.deactivated';

        return $this->jsonOrRedirect(
            $request,
            __($key, ['name' => $language->name]),
            route('admin.languages.index'),
        );
    }

    public function destroy(Request $request, Language $language, DeleteLanguage $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        $name = $language->name;

        try {
            ($delete)($language);
        } catch (LanguageNotDeletable $e) {
            return $this->jsonOrError(
                $request,
                __('languages.errors.'.$e->reason, ['name' => $name]),
                route('admin.languages.index'),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('languages.flash.deleted', ['name' => $name]),
            route('admin.languages.index'),
        );
    }

    /**
     * Kick off machine auto-translation of every untranslated key for
     * this language (the "auto-translate" plugin flow) — dispatched to
     * the queue since a full catalog run is minutes-long, not a single
     * request's worth of work. See {@see AutoTranslateLanguageJob}.
     */
    public function autoTranslate(Request $request, Language $language): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        $overwrite = $request->boolean('overwrite');

        AutoTranslateLanguageJob::dispatch($language->id, $overwrite);

        return $this->jsonOrRedirect(
            $request,
            __('languages.flash.auto_translate_started', ['name' => $language->name]),
            route('admin.languages.index'),
        );
    }

    public function export(Language $language, ExportTranslations $export): StreamedResponse
    {
        $this->authorize('settings.view');

        return ($export)($language);
    }

    public function import(Request $request, Language $language, ImportTranslations $import): RedirectResponse
    {
        $this->authorize('settings.update');

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ]);

        // Stage the upload under its real extension — the raw temp path
        // ends in `.tmp`, which the spreadsheet reader can't dispatch on.
        // (.txt uploads — common when a CSV is mime-guessed — read as CSV.)
        $ext = strtolower($request->file('file')->getClientOriginalExtension() ?: 'csv');
        if (! in_array($ext, ['xlsx', 'csv'], true)) {
            $ext = 'csv';
        }
        $stored = $request->file('file')->storeAs('imports/languages', Str::random(40).'.'.$ext, 'local');
        $abs    = Storage::disk('local')->path($stored);

        try {
            $result = ($import)($language, $abs);
        } finally {
            Storage::disk('local')->delete($stored);
        }

        return redirect()->route('admin.languages.index')->with('success', __('languages.flash.imported', [
            'name'     => $language->name,
            'imported' => $result['imported'],
            'skipped'  => $result['skipped'],
        ]));
    }
}
