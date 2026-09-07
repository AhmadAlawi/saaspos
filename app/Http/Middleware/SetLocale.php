<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active locale per request and shares the current
 * {@see Language} (for the layout's `dir`/switcher) to all views
 * (docs/features/multi-language.md §4).
 *
 * Order: session('locale') → user → active store → default language.
 * Only ACTIVE languages are honored; otherwise we keep the app default.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $languages = $this->activeLanguages();

        if ($languages->isNotEmpty()) {
            $candidates = array_filter([
                $request->session()->get('locale'),
                $request->user()?->locale,
                current_store()?->locale,
                $languages->firstWhere('is_default', true)?->code,
            ]);

            foreach ($candidates as $code) {
                if ($languages->firstWhere('code', $code)) {
                    app()->setLocale($code);
                    break;
                }
            }
        }

        view()->share('currentLanguage', $languages->firstWhere('code', app()->getLocale()));

        return $next($request);
    }

    /** @return \Illuminate\Support\Collection<int, Language> */
    private function activeLanguages(): \Illuminate\Support\Collection
    {
        try {
            if (! Schema::hasTable('languages')) {
                return collect();
            }
        } catch (\Throwable $e) {
            return collect();
        }

        return Language::query()->active()->ordered()->get();
    }
}
