<?php

namespace App\Providers;

use App\Actions\Accounting\PostCustomerPaymentEntry;
use App\Actions\Accounting\PostExpenseEntry;
use App\Actions\Accounting\PostPurchaseEntry;
use App\Actions\Accounting\PostSaleEntry;
use App\Actions\Accounting\PostSaleReturnEntry;
use App\Actions\Accounting\PostSaleVoidEntry;
use App\Actions\Accounting\PostShiftVarianceEntry;
use App\Actions\Accounting\PostStockAdjustmentEntry;
use App\Actions\Accounting\PostSupplierPaymentEntry;
use App\Actions\Reports\MarkDailyMetricsStale;
use App\Actions\Reports\RefreshDailyMetrics;
use App\Hooks\HookManager;
use Illuminate\Support\ServiceProvider;

/**
 * Wires up the action / filter dispatcher.
 *
 *   - `register()` binds {@see HookManager} as a singleton so every
 *      call into `app(HookManager::class)` (and therefore every
 *      `do_action` / `apply_filters` helper) shares the same registry.
 *
 *   - `boot()` is where built-in listeners belong. Plugin packages get
 *      their own service providers; this one is reserved for the
 *      hooks that ship with core (e.g. audit-log defaults).
 */
class HookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HookManager::class, fn () => new HookManager());
    }

    public function boot(HookManager $hooks): void
    {
        // ── Admin bell notifications ──────────────────────────────────────
        // Each listener turns a domain event into a database notification for
        // super-admins. Failures here never break the originating operation —
        // HookManager catches listener exceptions.

        $hooks->addAction('updater.update_available', function ($version, $release = null) {
            $key = 'update_available:'.$version;
            if (! admins_have_unread_notification($key)) {
                notify_admins(
                    $key,
                    __('notifications.update.title'),
                    __('notifications.update.message', ['version' => $version]),
                    'download',
                    route('admin.settings.updates.index'),
                    'updater.check',
                );
            }
        });

        $hooks->addAction('backup.failed', function ($log = null, $e = null) {
            notify_admins(
                'backup_failed:'.(optional($log)->id ?? ''),
                __('notifications.backup.title'),
                __('notifications.backup.message'),
                'database',
                route('admin.settings.backup.edit'),
                'backup.run',
            );
        });

        $hooks->addAction('sale.after_void', function ($sale) {
            $number = $sale->number ?? $sale->sale_number ?? ('#'.$sale->id);
            notify_admins(
                'sale_voided:'.$sale->id,
                __('notifications.void.title'),
                __('notifications.void.message', ['number' => $number]),
                'refund',
                url('/admin/sales/'.$sale->id),
                'sales.view_all',
            );
        });

        $hooks->addAction('sale.after_return', function ($return) {
            $saleId = $return->sale_id ?? null;
            $number = optional($return->sale ?? null)->number ?? ('#'.($saleId ?? $return->id));
            notify_admins(
                'sale_returned:'.$return->id,
                __('notifications.return.title'),
                __('notifications.return.message', ['number' => $number]),
                'refund',
                $saleId ? url('/admin/sales/'.$saleId) : null,
                'sales.view_all',
            );
        });

        // ── Daily-metrics upkeep ──────────────────────────────────────────
        // Keep the daily_metrics pre-aggregation (docs §13) consistent with
        // the trading it summarises. Closing a shift finalises that store's
        // day(s); a later void/return can mutate an already-settled day, so we
        // flag it stale for the nightly refresh (and the reader serves live in
        // the meantime). All best-effort — HookManager swallows failures so
        // these never break the originating operation.

        $hooks->addAction('shift.after_close', function ($shift) {
            $storeId = (int) ($shift->store_id ?? 0);
            if (! $storeId) {
                return;
            }
            $refresh = app(RefreshDailyMetrics::class);
            $dates = array_unique(array_filter([
                optional($shift->opened_at)->format('Y-m-d'),
                optional($shift->closed_at)->format('Y-m-d'),
            ]));
            foreach ($dates as $date) {
                $refresh($storeId, $date);
            }
        });

        $hooks->addAction('sale.after_void', function ($sale) {
            if (! empty($sale->store_id) && ! empty($sale->sale_date)) {
                app(MarkDailyMetricsStale::class)((int) $sale->store_id, $sale->sale_date);
            }
        });

        $hooks->addAction('sale.after_return', function ($return) {
            if (! empty($return->store_id) && ! empty($return->return_date)) {
                app(MarkDailyMetricsStale::class)((int) $return->store_id, $return->return_date);
            }
        });

        // ── Accounting: auto-post journal entries ─────────────────────────
        // A completed sale / received purchase generates its double-entry
        // journal (docs/features/accounting.md §5). Best-effort: a posting
        // failure is logged but never breaks checkout or goods-receipt — the
        // entry can be backfilled (the poster is idempotent per reference).

        $hooks->addAction('sale.after_complete', function ($sale) {
            try {
                app(PostSaleEntry::class)($sale);
            } catch (\Throwable $e) {
                report($e);
            }
        });

        $hooks->addAction('purchase.after_receive', function ($purchase) {
            try {
                app(PostPurchaseEntry::class)($purchase);
            } catch (\Throwable $e) {
                report($e);
            }
        });

        $hooks->addAction('expense.after_create', function ($expense) {
            try {
                app(PostExpenseEntry::class)($expense);
            } catch (\Throwable $e) {
                report($e);
            }
        });

        $hooks->addAction('customer_payment.after_create', function ($inserted, $customer) {
            try {
                app(PostCustomerPaymentEntry::class)((array) $inserted, $customer);
            } catch (\Throwable $e) {
                report($e);
            }
        });

        $hooks->addAction('supplier_payment.after_create', function ($inserted, $supplier) {
            try {
                app(PostSupplierPaymentEntry::class)((array) $inserted, $supplier);
            } catch (\Throwable $e) {
                report($e);
            }
        });

        $hooks->addAction('sale.after_void', function ($sale) {
            try {
                app(PostSaleVoidEntry::class)($sale);
            } catch (\Throwable $e) {
                report($e);
            }
        });

        $hooks->addAction('sale.after_return', function ($return) {
            try {
                app(PostSaleReturnEntry::class)($return);
            } catch (\Throwable $e) {
                report($e);
            }
        });

        $hooks->addAction('stock_adjustment.after_post', function ($adjustment) {
            try {
                app(PostStockAdjustmentEntry::class)($adjustment);
            } catch (\Throwable $e) {
                report($e);
            }
        });

        $hooks->addAction('shift.after_close', function ($shift) {
            try {
                app(PostShiftVarianceEntry::class)($shift);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
