<?php

namespace App\Actions\Dashboard;

use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\TaxComponent;
use App\Models\TaxGroup;
use App\Models\Terminal;
use App\Models\User;

/**
 * Builds the dashboard "Get your store ready" checklist — the handful of
 * things a brand-new install needs before it can ring up real sales. Each
 * step is a single cheap existence check against the relevant table and
 * links to the page where the owner completes it.
 *
 * Two groups:
 *   - `steps`    — ESSENTIAL. These genuinely block selling, and they are the
 *                  only ones that drive `percent`, so hitting 100% means "this
 *                  store can actually take a sale", not "you answered every
 *                  optional prompt".
 *   - `optional` — Recommended but not blocking (a supplier, a named customer,
 *                  extra staff). Rendered muted and NOT counted, so they can
 *                  never hold the bar below 100%.
 *
 * Deliberately excluded: units, payment methods, currencies and roles are
 * seeded by the installer, so a step for them would be a permanent tick that
 * inflates the bar. (`payment_method` stays in `optional` only because a store
 * may want to review/disable the seeded set.)
 *
 * Pure read; returns a plain array the dashboard blade renders. The card
 * auto-hides once `complete` is true (see DashboardController::index).
 */
class BuildSetupChecklist
{
    /**
     * @return array{
     *     steps: array<int, array{key:string,label:string,description:string,url:string,done:bool}>,
     *     optional: array<int, array{key:string,label:string,description:string,url:string,done:bool}>,
     *     done: int, total: int, percent: int, complete: bool
     * }
     */
    public function __invoke(): array
    {
        $company = Company::current();

        // ── Essential: these block a first real sale. Counted. ──────────
        $steps = [
            [
                'key'         => 'company_profile',
                'label'       => __('admin.dashboard.setup.steps.company_profile.label'),
                'description' => __('admin.dashboard.setup.steps.company_profile.desc'),
                // The logo lives on Branding & theme, not the company profile —
                // send them where the upload field actually is.
                'url'         => route('admin.settings.branding.edit'),
                // A logo is the one company field the installer can't fill in
                // for them, and it's what brands every receipt.
                'done'        => filled($company?->app_logo_path),
            ],
            [
                'key'         => 'tax_component',
                'label'       => __('admin.dashboard.setup.steps.tax_component.label'),
                'description' => __('admin.dashboard.setup.steps.tax_component.desc'),
                'url'         => route('admin.settings.tax.components.index'),
                // A tax group is built FROM components, so this comes first —
                // there's nothing to put in a group until a rate exists.
                'done'        => TaxComponent::query()->exists(),
            ],
            [
                'key'         => 'tax',
                'label'       => __('admin.dashboard.setup.steps.tax.label'),
                'description' => __('admin.dashboard.setup.steps.tax.desc'),
                'url'         => route('admin.settings.tax.groups.index'),
                'done'        => TaxGroup::query()->exists(),
            ],
            [
                'key'         => 'category',
                'label'       => __('admin.dashboard.setup.steps.category.label'),
                'description' => __('admin.dashboard.setup.steps.category.desc'),
                'url'         => route('admin.categories.index'),
                'done'        => Category::query()->exists(),
            ],
            [
                'key'         => 'product',
                'label'       => __('admin.dashboard.setup.steps.product.label'),
                'description' => __('admin.dashboard.setup.steps.product.desc'),
                'url'         => route('admin.products.create'),
                'done'        => Product::query()->exists(),
            ],
            [
                'key'         => 'stock',
                'label'       => __('admin.dashboard.setup.steps.stock.label'),
                'description' => __('admin.dashboard.setup.steps.stock.desc'),
                'url'         => route('admin.inventory.adjustments.create'),
                // A priced product with no stock still can't be sold.
                'done'        => StockLevel::query()->whereRaw('quantity > 0')->exists(),
            ],
            [
                'key'         => 'terminal',
                'label'       => __('admin.dashboard.setup.steps.terminal.label'),
                'description' => __('admin.dashboard.setup.steps.terminal.desc'),
                'url'         => route('admin.terminals.index'),
                'done'        => Terminal::query()->exists(),
            ],
        ];

        // ── Optional: recommended, never counted toward `percent`. ──────
        $optional = [
            [
                'key'         => 'payment_method',
                'label'       => __('admin.dashboard.setup.steps.payment_method.label'),
                'description' => __('admin.dashboard.setup.steps.payment_method.desc'),
                'url'         => route('admin.settings.payment-methods.edit'),
                'done'        => PaymentMethod::query()->active()->exists(),
            ],
            [
                'key'         => 'supplier',
                'label'       => __('admin.dashboard.setup.steps.supplier.label'),
                'description' => __('admin.dashboard.setup.steps.supplier.desc'),
                'url'         => route('admin.suppliers.create'),
                'done'        => Supplier::query()->exists(),
            ],
            [
                'key'         => 'customer',
                'label'       => __('admin.dashboard.setup.steps.customer.label'),
                'description' => __('admin.dashboard.setup.steps.customer.desc'),
                'url'         => route('admin.customers.create'),
                'done'        => Customer::query()->exists(),
            ],
            [
                'key'         => 'staff',
                'label'       => __('admin.dashboard.setup.steps.staff.label'),
                'description' => __('admin.dashboard.setup.steps.staff.desc'),
                'url'         => route('admin.users.index'),
                // The installer creates exactly one admin; a second user means
                // the owner has actually brought staff onto the system.
                'done'        => User::query()->count() > 1,
            ],
        ];

        $total = count($steps);
        $done  = count(array_filter($steps, fn ($s) => $s['done']));

        return [
            'steps'    => $steps,
            'optional' => $optional,
            'done'     => $done,
            'total'    => $total,
            'percent'  => $total > 0 ? (int) round($done / $total * 100) : 100,
            'complete' => $done === $total,
        ];
    }
}
