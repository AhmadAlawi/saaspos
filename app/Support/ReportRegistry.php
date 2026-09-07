<?php

namespace App\Support;

use App\Actions\Reports\ExportAgedReceivables;
use App\Actions\Reports\ExportDiscounts;
use App\Actions\Reports\ExportSalesByCashier;
use App\Actions\Reports\ExportSalesByCategory;
use App\Actions\Reports\ExportSalesByPaymentMethod;
use App\Actions\Reports\ExportSalesByProduct;
use App\Actions\Reports\ExportSalesSummary;
use App\Actions\Reports\ExportShiftsByCashier;
use App\Actions\Reports\ExportTopCustomers;
use App\Actions\Reports\ExportTopSuppliers;
use App\Services\Reports\AgedReceivablesQuery;
use App\Services\Reports\DiscountsReportQuery;
use App\Services\Reports\SalesByCashierQuery;
use App\Services\Reports\SalesByCategoryQuery;
use App\Services\Reports\SalesByPaymentMethodQuery;
use App\Services\Reports\SalesByProductQuery;
use App\Services\Reports\SalesSummaryQuery;
use App\Services\Reports\ShiftsByCashierQuery;
use App\Services\Reports\TopCustomersQuery;
use App\Services\Reports\TopSuppliersQuery;
use App\Actions\Reports\ExportTrialBalance;
use App\Services\Reports\TrialBalanceQuery;
use App\Actions\Reports\ExportProfitAndLoss;
use App\Actions\Reports\ExportBalanceSheet;
use App\Actions\Reports\ExportCashFlow;
use App\Services\Reports\ProfitAndLossQuery;
use App\Services\Reports\BalanceSheetQuery;
use App\Services\Reports\CashFlowQuery;

/**
 * The catalogue of report screens that support saving. Maps a stable
 * `report_key` (stored on saved_reports) to the route that renders it, its
 * translatable title, and the permission required to view it. Centralised
 * so saved reports, the save button, and the scheduler all agree on
 * what a report "is".
 *
 * Each entry also names the `query` service and `export` action that produce
 * the report headlessly (used by {@see \App\Services\Reports\ReportRunner}),
 * and its `date_mode` — `range` reports take a from/to window, `as_of`
 * reports take a single snapshot date.
 *
 * The keys deliberately match the `reports.<key>` lang namespaces already
 * in use.
 */
class ReportRegistry
{
    /** @return array<string, array{route: string, title_key: string, permission: string, query: class-string, export: class-string, date_mode: string}> */
    public static function all(): array
    {
        return [
            'sales_summary'          => ['route' => 'admin.reports.sales.index',                    'title_key' => 'reports.sales_summary.title',          'permission' => 'reports.view_financial', 'query' => SalesSummaryQuery::class,          'export' => ExportSalesSummary::class,          'date_mode' => 'range'],
            'sales_by_product'       => ['route' => 'admin.reports.sales-by-product.index',         'title_key' => 'reports.sales_by_product.title',       'permission' => 'reports.view_financial', 'query' => SalesByProductQuery::class,        'export' => ExportSalesByProduct::class,        'date_mode' => 'range'],
            'sales_by_cashier'       => ['route' => 'admin.reports.sales-by-cashier.index',         'title_key' => 'reports.sales_by_cashier.title',       'permission' => 'reports.view_sales',     'query' => SalesByCashierQuery::class,        'export' => ExportSalesByCashier::class,        'date_mode' => 'range'],
            'sales_by_payment_method' => ['route' => 'admin.reports.sales-by-payment-method.index', 'title_key' => 'reports.sales_by_payment_method.title', 'permission' => 'reports.view_sales',    'query' => SalesByPaymentMethodQuery::class,  'export' => ExportSalesByPaymentMethod::class,  'date_mode' => 'range'],
            'sales_by_category'      => ['route' => 'admin.reports.sales-by-category.index',        'title_key' => 'reports.sales_by_category.title',      'permission' => 'reports.view_sales',     'query' => SalesByCategoryQuery::class,       'export' => ExportSalesByCategory::class,       'date_mode' => 'range'],
            'discounts'              => ['route' => 'admin.reports.discounts.index',                'title_key' => 'reports.discounts.title',              'permission' => 'reports.view_sales',     'query' => DiscountsReportQuery::class,       'export' => ExportDiscounts::class,             'date_mode' => 'range'],
            'top_customers'          => ['route' => 'admin.reports.top-customers.index',            'title_key' => 'reports.top_customers.title',          'permission' => 'reports.view_customers', 'query' => TopCustomersQuery::class,          'export' => ExportTopCustomers::class,          'date_mode' => 'range'],
            'top_suppliers'          => ['route' => 'admin.reports.top-suppliers.index',            'title_key' => 'reports.top_suppliers.title',          'permission' => 'reports.view_suppliers', 'query' => TopSuppliersQuery::class,          'export' => ExportTopSuppliers::class,          'date_mode' => 'range'],
            'shifts_by_cashier'      => ['route' => 'admin.reports.shifts-by-cashier.index',        'title_key' => 'reports.shifts_by_cashier.title',      'permission' => 'reports.view_employees', 'query' => ShiftsByCashierQuery::class,       'export' => ExportShiftsByCashier::class,       'date_mode' => 'range'],
            'aged_receivables'       => ['route' => 'admin.reports.aged-receivables.index',         'title_key' => 'reports.aged_receivables.title',       'permission' => 'reports.view_financial', 'query' => AgedReceivablesQuery::class,       'export' => ExportAgedReceivables::class,       'date_mode' => 'as_of'],
            'trial_balance'          => ['route' => 'admin.reports.trial-balance.index',            'title_key' => 'reports.trial_balance.title',          'permission' => 'reports.view_financial', 'query' => TrialBalanceQuery::class,          'export' => ExportTrialBalance::class,          'date_mode' => 'as_of'],
            'profit_and_loss'        => ['route' => 'admin.reports.profit-and-loss.index',          'title_key' => 'reports.pnl.title',                    'permission' => 'reports.view_financial', 'query' => ProfitAndLossQuery::class,         'export' => ExportProfitAndLoss::class,         'date_mode' => 'range'],
            'balance_sheet'          => ['route' => 'admin.reports.balance-sheet.index',            'title_key' => 'reports.balance_sheet.title',          'permission' => 'reports.view_financial', 'query' => BalanceSheetQuery::class,          'export' => ExportBalanceSheet::class,          'date_mode' => 'as_of'],
            'cash_flow'              => ['route' => 'admin.reports.cash-flow.index',                'title_key' => 'reports.cash_flow.title',              'permission' => 'reports.view_financial', 'query' => CashFlowQuery::class,              'export' => ExportCashFlow::class,              'date_mode' => 'range'],
        ];
    }

    /** Parameter keys that may be persisted from a report's query string. */
    public const ALLOWED_PARAMS = ['period', 'from', 'to', 'store_id', 'as_of'];

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /** @return array{route: string, title_key: string, permission: string}|null */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /** Reverse-lookup the report key for an index route name (or null). */
    public static function keyForRoute(string $route): ?string
    {
        foreach (self::all() as $key => $meta) {
            if ($meta['route'] === $route) {
                return $key;
            }
        }

        return null;
    }

    /** Keep only whitelisted, scalar parameters. */
    public static function sanitizeParams(array $params): array
    {
        return collect($params)
            ->only(self::ALLOWED_PARAMS)
            ->filter(fn ($v) => is_scalar($v) && $v !== '')
            ->map(fn ($v) => (string) $v)
            ->all();
    }
}
