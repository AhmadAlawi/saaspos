<?php

namespace App\View\Components\Admin;

use App\Models\Language;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Top-bar language switcher. Lists active languages and POSTs to the
 * locale-switch route. Renders nothing when fewer than two languages are
 * active (nothing to switch between).
 */
class LanguageSwitcher extends Component
{
    /** @var Collection<int, Language> */
    public Collection $languages;

    public ?Language $current;

    public function __construct()
    {
        $this->languages = $this->activeLanguages();
        $this->current   = $this->languages->firstWhere('code', app()->getLocale());
    }

    public function shouldRender(): bool
    {
        return $this->languages->count() > 1;
    }

    public function render(): View
    {
        return view('components.admin.language-switcher');
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
