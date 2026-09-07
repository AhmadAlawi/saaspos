<?php

namespace App\Console\Commands;

use App\Actions\Reports\RefreshDailyMetrics;
use App\Models\DailyMetric;
use App\Models\Store;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the daily_metrics pre-aggregation. Runs nightly (routes/console.php)
 * to finalise yesterday, refresh a small trailing window (catching late
 * returns/voids on recent days), and recompute any day flagged `stale` by a
 * void/return on an older, already-settled day.
 *
 * Manual: `--date=YYYY-MM-DD` recomputes one date for every active store.
 */
class RefreshDailyMetricsCommand extends Command
{
    protected $signature = 'pos:refresh-daily-metrics
        {--date= : Recompute this exact date (Y-m-d) for all active stores}
        {--days=3 : Trailing days (incl. today) to recompute for every store}';

    protected $description = 'Rebuild the daily_metrics pre-aggregation for recent and stale days.';

    public function handle(RefreshDailyMetrics $refresh): int
    {
        $storeIds = Store::query()->where('is_active', true)->pluck('id');
        $done     = 0;

        if ($dateOpt = $this->option('date')) {
            try {
                $date = CarbonImmutable::parse($dateOpt)->toDateString();
            } catch (\Throwable) {
                $this->error("Invalid --date '{$dateOpt}'.");

                return self::FAILURE;
            }

            foreach ($storeIds as $storeId) {
                $refresh((int) $storeId, $date);
                $done++;
            }

            $this->info("Refreshed {$done} store-day(s) for {$date}.");

            return self::SUCCESS;
        }

        $days  = max(1, (int) $this->option('days'));
        $today = CarbonImmutable::now();
        $seen  = [];

        // 1. Trailing window for every active store.
        foreach ($storeIds as $storeId) {
            for ($i = 0; $i < $days; $i++) {
                $date = $today->subDays($i)->toDateString();
                $refresh((int) $storeId, $date);
                $seen["{$storeId}|{$date}"] = true;
                $done++;
            }
        }

        // 2. Any stale (store, date) outside that window — late voids/returns
        //    that touched an older, already-settled day.
        $stale = DB::table('daily_metrics')
            ->where('refresh_status', DailyMetric::STATUS_STALE)
            ->select('store_id', 'date')
            ->distinct()
            ->get();

        foreach ($stale as $row) {
            $date = CarbonImmutable::parse($row->date)->toDateString();
            if (isset($seen["{$row->store_id}|{$date}"])) {
                continue;
            }
            $refresh((int) $row->store_id, $date);
            $done++;
        }

        $this->info("Refreshed {$done} store-day(s).");

        return self::SUCCESS;
    }
}
