<?php

namespace App\View\Components;

use App\Models\Company;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * <x-cashier-layout> — full-screen cashier shell.
 *
 * Unlike the admin layout, the cashier surface claims the whole
 * viewport: no sidebar, no admin topbar, no breadcrumb, no footer.
 * The cashier sees a thin brand strip + an immediately-usable POS
 * working area. This is the same layout used by Square / Toast /
 * Lightspeed — focused-task UI, no extraneous chrome.
 *
 * Loads the same `admin.css` bundle as the rest of the back-office
 * so all the design tokens, button styles, etc. carry over — we
 * just don't render the admin chrome.
 */
class CashierLayout extends Component
{
    public ?string $brandColor = null;
    public ?string $brandTextColor = null;
    public ?string $appLogoUrl = null;
    public ?string $appLogoDarkUrl = null;
    public ?string $faviconUrl = null;
    public string $appName = '';
    public string $themeDefault = 'light';

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
            // Prefer the cashier-specific theme default over the
            // company-wide one. Only when it's not "auto" — auto means
            // "follow the OS / admin default", which the UI store
            // already handles via its own fallback chain.
            $cashier = $company->cashier();
            $cashierTheme = $cashier['theme_default'] ?? 'auto';
            $this->themeDefault   = $cashierTheme !== 'auto'
                ? $cashierTheme
                : ($company->theme_default ?: 'light');
        }
    }

    /** Mirrors AdminLayout::userContext so `$user.can()` still works. */
    public function userContext(): string
    {
        $user = auth()->user();

        return json_encode([
            'id'             => $user?->id,
            'is_super_admin' => (bool) ($user?->is_super_admin ?? false),
            'permissions'    => $user ? $user->effectivePermissions() : [],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function render(): View
    {
        return view('components.cashier-layout');
    }
}
