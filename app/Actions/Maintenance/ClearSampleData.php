<?php

namespace App\Actions\Maintenance;

use App\Actions\Settings\RunBackup;
use App\Models\BackupLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes all business / transactional / sample data so a customer who installed
 * with demo data can start on a clean slate — WITHOUT losing the things they
 * (or the installer) set up: company profile, stores, users/roles/permissions,
 * settings, currencies + lookups, tax config, payment methods, terminals, and
 * the chart of accounts.
 *
 * It works off an explicit DELETE list (below): anything NOT listed is
 * preserved, so a newly-added or unknown table can never be silently wiped.
 * The list is ordered child → parent so it succeeds even when foreign keys are
 * enforced (SQLite under test); production MySQL additionally has FK checks
 * disabled for the duration, matching the backup/restore machinery.
 *
 * A restore-grade backup is taken FIRST by default (best-effort — a host that
 * can't back up still gets the wipe it asked for, but the caller is told).
 *
 * Hooks: `sample_data.before_clear` · `sample_data.after_clear` ($cleared).
 */
class ClearSampleData
{
    public function __construct(private readonly RunBackup $runBackup) {}

    /**
     * Business tables to empty, ordered so children are deleted before the
     * rows they reference. EVERYTHING ELSE IS PRESERVED — do not turn this into
     * a "truncate all except" list.
     */
    private const TABLES = [
        // ── Deep children / ledgers / logs ────────────────────────────────
        'stock_movements',
        'sale_return_items', 'sale_returns', 'sale_discounts', 'sale_payments', 'sale_items',
        'purchase_return_items', 'purchase_returns', 'purchase_payments', 'purchase_items',
        'stock_transfer_items', 'stock_take_items', 'stock_adjustment_items',
        'product_batches', 'product_stock_levels', 'product_store_prices',
        'product_kit_items', 'product_images', 'product_variants',
        'customer_credit_transactions', 'customer_addresses',
        'tax_exemption_certificates', 'tax_exemptions',
        'journal_lines',
        'cash_drawer_entries',
        'receipt_public_links',
        'pos_payment_sessions', 'payment_webhook_log',
        'scheduled_report_runs',
        'import_job_errors',
        'ai_chat_history', 'ai_reorder_suggestions', 'ai_usage_logs',
        'daily_metrics', 'sync_logs', 'print_logs', 'whatsapp_logs',
        'notifications', 'attachments', 'audit_logs',

        // ── Documents / headers ───────────────────────────────────────────
        'sales', 'store_sale_counters',
        'purchases',
        'expenses',
        'stock_transfers', 'stock_takes', 'stock_adjustments',
        'journal_entries',
        'shifts',
        'saved_reports', 'scheduled_reports',
        'import_jobs',

        // ── Entities / catalogue ──────────────────────────────────────────
        'products',
        'customers',
        'suppliers',
        'fiscal_periods',

        // ── Parents / catalogue roots ─────────────────────────────────────
        'categories', 'brands',
        'customer_groups',
        'fiscal_years',
    ];

    /**
     * @param  bool  $backup  take a restore-grade backup first (recommended)
     * @return array{cleared: array<string,int>, backup: BackupLog|null}
     */
    public function __invoke(?int $userId = null, bool $backup = true): array
    {
        do_action('sample_data.before_clear');

        $backupLog = null;
        if ($backup) {
            try {
                $backupLog = ($this->runBackup)('pre-clear-sample', $userId);
            } catch (\Throwable $e) {
                report($e); // best-effort — surface via the null log, don't block the wipe
            }
        }

        $driver  = DB::connection()->getDriverName();
        $cleared = [];

        DB::unprepared($driver === 'sqlite' ? 'PRAGMA foreign_keys=OFF;' : 'SET FOREIGN_KEY_CHECKS=0;');
        try {
            foreach (self::TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $deleted = DB::table($table)->delete();
                if ($deleted > 0) {
                    $cleared[$table] = $deleted;
                }
            }
        } finally {
            DB::unprepared($driver === 'sqlite' ? 'PRAGMA foreign_keys=ON;' : 'SET FOREIGN_KEY_CHECKS=1;');
        }

        do_action('sample_data.after_clear', $cleared);

        return ['cleared' => $cleared, 'backup' => $backupLog];
    }
}
