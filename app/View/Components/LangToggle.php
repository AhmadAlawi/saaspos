<?php

namespace App\View\Components;

use App\Models\Language;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * One-tap language toggle for cashier + kiosk — simpler than admin's full
 * multi-language dropdown ({@see \App\View\Components\Admin\LanguageSwitcher})
 * since staff only need to flip between two locales day to day. Renders
 * nothing when fewer than two languages are active. With 3+ active
 * languages it still renders a single button that steps to the next one
 * in sort order — no dropdown, so it stays a one-tap action on a
 * touchscreen till.
 */
class LangToggle extends Component
{
    public ?Language $current;

    public ?Language $target;

    public function __construct(public string $buttonClass = 'cashier-icon-btn')
    {
        $languages = $this->activeLanguages();

        $this->current = $languages->firstWhere('code', app()->getLocale()) ?? $languages->first();
        $this->target  = $this->current
            ? ($languages->firstWhere('id', '!=', $this->current->id) ?? $this->current)
            : null;
    }

    public function shouldRender(): bool
    {
        return $this->target !== null && $this->target->id !== $this->current?->id;
    }

    public function render(): View
    {
        return view('components.lang-toggle');
    }

    /** @return Collection<int, Language> */
    private function activeLanguages(): Collection
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
