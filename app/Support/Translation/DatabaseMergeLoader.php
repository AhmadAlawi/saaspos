<?php

namespace App\Support\Translation;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decorates Laravel's file `Loader`, merging per-locale DB overrides
 * (the `translations` table) on top of the `lang/*.php` defaults so a
 * customer's translations win while untranslated keys fall back to
 * English (docs/features/multi-language.md §3).
 *
 * Overrides are cached per (locale, group), keyed by a global version
 * counter that any import/edit bumps — so one write invalidates every
 * stale entry without per-key cache juggling (shared-hosting friendly).
 */
class DatabaseMergeLoader implements Loader
{
    public function __construct(private Loader $inner) {}

    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->inner->load($locale, $group, $namespace);

        // Package/vendor namespaces are passed through untouched.
        if ($namespace !== null && $namespace !== '*') {
            return $lines;
        }

        $overrides = $this->overridesFor($locale, $group);
        if ($overrides === []) {
            return $lines;
        }

        // JSON strings (group '*') are keyed by the full sentence — merge
        // flat. Group files use dot-paths — set into the nested array.
        if ($group === '*') {
            return array_merge($lines, $overrides);
        }

        foreach ($overrides as $key => $value) {
            Arr::set($lines, $key, $value);
        }

        return $lines;
    }

    /** @return array<string, string> flat [dot-key => value] for the (locale, group) */
    private function overridesFor(string $locale, string $group): array
    {
        try {
            if (! Schema::hasTable('translations')) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        $version = (int) Cache::get('translations.version', 1);

        return Cache::remember(
            "tr:v{$version}:{$locale}:{$group}",
            now()->addHours(6),
            fn () => DB::table('translations')
                ->where('locale', $locale)
                ->where('group', $group)
                ->pluck('value', 'key')
                ->all(),
        );
    }

    public function addNamespace($namespace, $hint)
    {
        $this->inner->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->inner->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->inner->namespaces();
    }

    /** Invalidate every cached override set (call after import/edit). */
    public static function bumpVersion(): void
    {
        Cache::forever('translations.version', (int) Cache::get('translations.version', 1) + 1);
    }
}
