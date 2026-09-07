<?php

namespace App\Providers;

use App\Services\Translation\AutoTranslator;
use App\Services\Translation\GoogleAutoTranslator;
use App\Support\Translation\DatabaseMergeLoader;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Layer per-locale DB translation overrides over the lang/*.php
        // defaults (docs/features/multi-language.md §3). Extends the
        // deferred `translation.loader` binding — the extender is queued
        // and applied when the translator first resolves it.
        $this->app->extend('translation.loader', fn ($loader) => new DatabaseMergeLoader($loader));

        // Machine-translation backend for auto-populating a new language
        // (see AutoTranslateLanguage) — bound behind an interface so the
        // provider can be swapped without touching the action/job.
        $this->app->bind(AutoTranslator::class, GoogleAutoTranslator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // System-wide default pagination view — every `->links()` call
        // across the admin renders with the same chrome (caption +
        // numbered nav) instead of Laravel's stock Tailwind 3 markup.
        \Illuminate\Pagination\Paginator::defaultView('vendor.pagination.system');
        \Illuminate\Pagination\Paginator::defaultSimpleView('vendor.pagination.system');

        // Site sits behind Cloudflare; nginx derives its own HTTPS fastcgi
        // param purely from its raw connection to the edge, not from
        // X-Forwarded-Proto/CF-Visitor. When that hop isn't TLS (edge/PoP
        // variance), route()/url() silently emit http:// links — invisible
        // on browsers with a cached HSTS record for the domain (silently
        // upgraded), but a hard mixed-content block on any device without
        // one. APP_URL is always https here and plain http never serves
        // real content (nginx force-redirects it), so just force it.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
