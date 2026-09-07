<?php

use App\Actions\Demo\ResetDemoData;
use App\Actions\Inventory\CheckLowStock;
use App\Actions\Licensing\RecheckLicense;
use App\Actions\Sales\RetryFailedGatewayReversals;
use App\Actions\Settings\EmailBackupCopy;
use App\Actions\Settings\PruneBackups;
use App\Actions\Settings\RunBackup;
use App\Actions\Updater\AutoInstallIfEligible;
use App\Actions\Updater\CheckForUpdate;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/* ───────────────────────── Backups ─────────────────────────
 * Three schedules — only the one that matches the configured
 * frequency actually runs (the `when()` gate filters the rest).
 * `withoutOverlapping()` stops a slow backup from being kicked
 * off twice if the previous one is still going.
 */
$runBackup = function () {
    $log = app(RunBackup::class)('scheduled');
    app(PruneBackups::class)();
    // Email a copy to the company (opt-in; attaches when small enough).
    app(EmailBackupCopy::class)($log);
};

Schedule::call($runBackup)
    ->dailyAt('02:00')
    ->when(fn () => app_backup()['enabled'] && app_backup()['frequency'] === 'daily')
    ->name('pos:backup-daily');

Schedule::call($runBackup)
    ->weeklyOn(0, '02:00')
    ->when(fn () => app_backup()['enabled'] && app_backup()['frequency'] === 'weekly')
    ->name('pos:backup-weekly');

Schedule::call($runBackup)
    ->monthlyOn(1, '02:00')
    ->when(fn () => app_backup()['enabled'] && app_backup()['frequency'] === 'monthly')
    ->name('pos:backup-monthly');

/* ───────────────────────── Updates ─────────────────────────
 * Daily feed check — only when the customer left auto-check on.
 * Read-only: records whether a newer version exists so the admin
 * banner can show it. Never downloads or installs anything.
 */
Schedule::call(function () {
    app(CheckForUpdate::class)();
    app(AutoInstallIfEligible::class)();
})
    ->dailyAt('03:00')
    ->when(fn () => app_updates()['auto_check'] || app_updates()['auto_install'])
    ->name('pos:update-check');

/* ───────────────────────── Low stock ───────────────────────
 * Daily sweep for products at/below reorder level → one admin
 * bell notification (deduped while the situation persists).
 */
Schedule::call(fn () => app(CheckLowStock::class)())
    ->dailyAt('06:00')
    ->name('pos:low-stock-check');

/* ─────────────────────── Daily metrics ─────────────────────
 * Nightly rebuild of the daily_metrics pre-aggregation: finalise
 * yesterday, refresh a short trailing window, and recompute any
 * day marked stale by a late void/return. Dashboards + trend
 * reports read this for settled days (live fallback otherwise).
 */
Schedule::command('pos:refresh-daily-metrics')
    ->dailyAt('01:30')
    ->name('pos:daily-metrics')
    ->withoutOverlapping();

/* ───────────────────────── License re-check ────────────────
 * Only registered when panel-side verification is switched on
 * (config/pos.php → license.recheck, off by default) — otherwise a
 * self-hosted install would run a daily no-op that phones home.
 *
 * When on: runs daily but the action only actually hits the server
 * every ~30 days (its own cadence guard), so a missed cron day
 * doesn't matter. NEVER locks the app — a failed check only records
 * an error the admin banner surfaces. Offline installs are skipped.
 */
if (config('pos.license.recheck')) {
    Schedule::call(fn () => app(RecheckLicense::class)())
        ->dailyAt('04:00')
        ->name('pos:license-recheck');
}

/* ───────────────────────── Demo reset ──────────────────────
 * Public-demo housekeeping ONLY (gated by POS_DEMO_MODE). Wipes
 * whatever visitors did and restores the captured baseline so the
 * demo is pristine each morning — visitors stay free to add data,
 * the junk just doesn't survive the night. No-ops if no baseline
 * has been captured (`pos:demo-snapshot`).
 */
Schedule::call(fn () => app(ResetDemoData::class)())
    ->dailyAt('02:00')
    ->when(fn () => pos_is_demo())
    ->name('pos:demo-reset');

/* ──────────────────── Gateway refund retries ───────────────
 * Re-attempts card/Stripe/Razorpay reversals that failed at
 * refund time (gateway outage, transient error). Attempts are
 * capped per return, so a permanently-unrefundable charge stops
 * retrying and waits for a manual "Retry reversal".
 */
Schedule::call(fn () => app(RetryFailedGatewayReversals::class)())
    ->everyFiveMinutes()
    ->name('pos:gateway-refund-retry')
    ->withoutOverlapping();

/* ──────────────────── Scheduled reports ─────────────────────
 * Every minute, deliver any report schedules that have come due
 * (email the generated PDF/Excel/CSV to their recipients). Cheap
 * when nothing is due — one indexed query. Runs synchronously
 * (no queue) to stay shared-hosting friendly.
 */
Schedule::command('pos:run-scheduled-reports')
    ->everyMinute()
    ->name('pos:scheduled-reports')
    ->withoutOverlapping();
