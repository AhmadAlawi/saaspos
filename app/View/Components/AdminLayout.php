<?php

namespace App\View\Components;

use App\Models\Company;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * <x-admin-layout :active="'dashboard'" :crumbs="[['label' => 'Dashboard']]">
 *     ... page content ...
 * </x-admin-layout>
 *
 * Receives the active sidebar item id + the breadcrumb array. All PHP logic
 * (nav schema lookup, active-state marking, command-palette index assembly)
 * lives in this class so the Blade template stays pure presentation.
 */
class AdminLayout extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $nav;

    public ?string $brandColor = null;
    public ?string $brandTextColor = null;
    public ?string $faviconUrl = null;
    public ?string $appLogoUrl = null;
    public ?string $appLogoDarkUrl = null;
    public ?string $appLogoHalfUrl = null;
    public ?string $appLogoHalfDarkUrl = null;
    public string $appName = '';
    public string $footerText = '';
    public string $themeDefault = 'light';

    /**
     * @param  array<int, array{label: string, href?: ?string}>  $crumbs
     */
    public function __construct(
        public string $active = '',
        public array $crumbs = [],
        public string $title = '',
    ) {
        $this->appName = (string) config('app.name');
        $this->nav = $this->buildNav($active);
        $this->loadBranding();
    }

    /** Read the company's branding/theme for head injection (one cheap row). */
    private function loadBranding(): void
    {
        try {
            if (! Schema::hasTable('company')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        if ($company = Company::current()) {
            $this->brandColor         = $company->brand_color;
            $this->brandTextColor     = $company->brand_text_color;
            $this->faviconUrl         = $company->favicon_url;
            $this->appLogoUrl         = $company->app_logo_url;
            $this->appLogoDarkUrl     = $company->app_logo_dark_url;
            $this->appLogoHalfUrl     = $company->app_logo_half_url;
            $this->appLogoHalfDarkUrl = $company->app_logo_half_dark_url;
            $this->appName            = $company->display_app_name;
            $this->footerText         = (string) ($company->footer_text ?? '');
            $this->themeDefault       = $company->theme_default ?: 'light';
        }
    }

    public function render(): View
    {
        return view('components.admin-layout');
    }

    /**
     * JSON payload for window.__user — drives Alpine's `$user.can()` so the
     * UI can hide controls the user lacks permission for. UI gating only;
     * the server always re-checks via policies/gates.
     */
    public function userContext(): string
    {
        $user = auth()->user();

        return json_encode([
            'id'             => $user?->id,
            'is_super_admin' => (bool) ($user?->is_super_admin ?? false),
            'permissions'    => $user ? $user->effectivePermissions() : [],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * JSON payload for window.POS_CMD_INDEX — drives the command palette.
     *
     * Three scope groups are pre-indexed:
     *   - pages     : every sidebar nav item the user has access to.
     *   - products  : up to 100 most-recently-updated active products.
     *   - orders    : up to 100 most-recently-created sales.
     *   - customers : up to 100 most-recently-updated active customers.
     *
     * The cap keeps the payload small (a few hundred KB at the top end)
     * while still making the palette useful as a launcher. Full-catalog
     * search across thousands of rows is a future server-side endpoint;
     * this static preload covers the common "jump to this product /
     * this order I just rang up" UX.
     */
    public function commandIndex(): string
    {
        $rows = [];

        foreach ($this->nav as $section) {
            $this->collectNavLeaves($section['items'], $section['label'], null, $rows);
        }

        $user = auth()->user();

        if ($user?->can('products.view')) {
            $products = \App\Models\Product::query()
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->limit(100)
                ->get(['id', 'name', 'sku', 'barcode']);
            $boxSvg = Icon::pathsFor('box');
            foreach ($products as $p) {
                $sub = trim(implode(' · ', array_filter([
                    $p->sku ? "SKU {$p->sku}" : null,
                    $p->barcode,
                ])));
                $rows[] = [
                    'scope' => 'products',
                    'group' => 'Products',
                    'title' => $p->name,
                    'sub'   => $sub !== '' ? $sub : 'Product',
                    'href'  => route('admin.products.edit', $p),
                    'icon'  => 'box',
                    'svg'   => $boxSvg,
                ];
            }
        }

        if ($user?->can('sales.view_own')) {
            $sales = \App\Models\Sale::query()
                ->with('customer:id,name')
                ->orderByDesc('id')
                ->limit(100)
                ->get(['id', 'number', 'customer_id', 'grand_total', 'created_at']);
            $receiptSvg = Icon::pathsFor('receipt');
            foreach ($sales as $s) {
                $sub = trim(implode(' · ', array_filter([
                    $s->customer?->name,
                    optional($s->created_at)->format('Y-m-d'),
                ])));
                $rows[] = [
                    'scope' => 'orders',
                    'group' => 'Sales',
                    'title' => (string) ($s->number ?? "Sale #{$s->id}"),
                    'sub'   => $sub !== '' ? $sub : 'Sale',
                    'href'  => route('admin.sales.show', $s),
                    'icon'  => 'receipt',
                    'svg'   => $receiptSvg,
                ];
            }
        }

        if ($user?->can('customers.view')) {
            $customers = \App\Models\Customer::query()
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->limit(100)
                ->get(['id', 'code', 'name', 'phone', 'email']);
            $customersSvg = Icon::pathsFor('customers');
            foreach ($customers as $c) {
                // Masked: this index is inlined into a <meta> tag on EVERY admin
                // page, so an unmasked address here leaks the whole customer
                // list to anyone who views source on the demo.
                $sub = trim(implode(' · ', array_filter([
                    $c->code,
                    $c->phone ? \App\Support\PhoneFormatter::pretty($c->phone) : null,
                    $c->email ? $c->display_email : null,
                ])));
                $rows[] = [
                    'scope' => 'customers',
                    'group' => 'Customers',
                    'title' => $c->name,
                    'sub'   => $sub !== '' ? $sub : 'Customer',
                    'href'  => route('admin.customers.edit', $c),
                    'icon'  => 'customers',
                    'svg'   => $customersSvg,
                ];
            }
        }

        return json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Resolves the nav config, drops items the current user lacks the
     * `permission` for (and any section left empty), translates the
     * section + item labels (so the sidebar + command palette localize),
     * and stamps each surviving item with its active flag.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildNav(string $activeId): array
    {
        $user   = auth()->user();
        $result = [];

        foreach (config('admin-nav', []) as $section) {
            $items = [];
            foreach ($section['items'] as $item) {
                $processed = $this->processNavItem($item, $activeId, $user);
                if ($processed !== null) {
                    $items[] = $processed;
                }
            }

            if ($items !== []) {
                $section['label'] = $this->navLabel('admin.nav.sections.'.\Illuminate\Support\Str::lower($section['label']), $section['label']);
                $section['items'] = $items;
                $result[] = $section;
            }
        }

        return $result;
    }

    /**
     * Resolve one nav item (recursively). Groups (items with `items`) keep
     * any child the user may see and inherit active state from them; a
     * group whose children are all dropped disappears entirely. Leaves are
     * dropped if their permission check fails. Returns null when the item
     * should not render. Handles arbitrary nesting depth so the collapsible
     * sub-groups (e.g. Inventory → Inventory reports) get permission-filtered
     * + active-marked like everything else.
     */
    private function processNavItem(array $item, string $activeId, $user): ?array
    {
        if (isset($item['items'])) {
            $children = [];
            foreach ($item['items'] as $child) {
                $processed = $this->processNavItem($child, $activeId, $user);
                if ($processed !== null) {
                    $children[] = $processed;
                }
            }
            if ($children === []) {
                return null;
            }
            $item['items']     = $children;
            $item['is_active'] = collect($children)->contains('is_active', true);
            $item['label']     = $this->navLabel('admin.nav.items.'.($item['id'] ?? ''), $item['label']);
            return $item;
        }

        if (! $this->userMaySee($item, $user)) {
            return null;
        }
        $item['is_active'] = $this->isActive($item, $activeId);
        $item['label']     = $this->navLabel('admin.nav.items.'.($item['id'] ?? ''), $item['label']);
        return $item;
    }

    /**
     * `permission_any` (array) passes when the user holds ANY of the listed
     * permissions — used by the Reports hub, which mirrors
     * ReportsHubController's "any report viewer may enter" rule. Otherwise
     * the single `permission` string applies. No permission key = always visible.
     */
    private function userMaySee(array $item, $user): bool
    {
        if (! empty($item['permission_any'])) {
            foreach ((array) $item['permission_any'] as $permission) {
                if ($user?->can($permission)) {
                    return true;
                }
            }

            return false;
        }

        if (! empty($item['permission'])) {
            return $user?->can($item['permission']) ?? false;
        }

        return true;
    }

    /**
     * A row lights up for its own id, or for any page listed in `active_ids` —
     * which is how "All reports" stays highlighted while you're on one of the
     * individual report screens it now hosts.
     */
    private function isActive(array $item, string $activeId): bool
    {
        if (($item['id'] ?? null) === $activeId) {
            return true;
        }

        return \in_array($activeId, $item['active_ids'] ?? [], true);
    }

    /**
     * Flatten the (possibly deeply-nested) nav into command-palette leaf
     * rows. Only items with an `href` become rows; group containers are
     * descended into, using the nearest group's label as the row's group.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function collectNavLeaves(array $items, string $sectionLabel, ?string $groupLabel, array &$rows): void
    {
        foreach ($items as $item) {
            if (isset($item['items'])) {
                $this->collectNavLeaves($item['items'], $sectionLabel, $item['label'], $rows);
            } elseif (! empty($item['href'])) {
                $rows[] = [
                    'scope' => 'pages',
                    'group' => $groupLabel ?? $sectionLabel,
                    'title' => $item['label'],
                    'sub'   => $sectionLabel,
                    'href'  => $item['href'],
                    'icon'  => $item['icon'] ?? 'dashboard',
                    'svg'   => Icon::pathsFor($item['icon'] ?? 'dashboard'),
                ];
            }
        }
    }

    /** Translate a nav label via its key, falling back to the config text. */
    private function navLabel(string $key, string $fallback): string
    {
        return trans()->has($key) ? (string) __($key) : $fallback;
    }
}
