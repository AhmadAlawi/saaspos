<?php

namespace App\View\Components;

use App\Models\Company;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * <x-auth-layout :title="..."> ... </x-auth-layout>
 *
 * Minimal full-viewport shell for login / password-reset / MFA pages.
 * Loads app.css (no admin chrome) + the theme toggle in the top-right.
 * Also loads company branding so the login page can show the app logo
 * and accent color without a separate controller query.
 */
class AuthLayout extends Component
{
    public ?string $brandColor = null;
    public ?string $brandTextColor = null;
    public ?string $faviconUrl = null;
    public ?string $appLogoUrl = null;
    public ?string $appLogoDarkUrl = null;
    public string $appName = '';
    public string $footerText = '';

    public function __construct(public string $title = '')
    {
        $this->appName = (string) config('app.name');
        $this->loadBranding();
    }

    private function loadBranding(): void
    {
        try {
            if (! Schema::hasTable('company')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        if ($company = Company::current()) {
            $this->brandColor     = $company->brand_color;
            $this->brandTextColor = $company->brand_text_color;
            $this->faviconUrl     = $company->favicon_url;
            $this->appLogoUrl     = $company->app_logo_url;
            $this->appLogoDarkUrl = $company->app_logo_dark_url;
            $this->appName        = $company->display_app_name;
            $this->footerText     = (string) ($company->footer_text ?? '');
        }
    }

    public function render(): View
    {
        return view('components.auth-layout');
    }
}
