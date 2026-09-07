<?php

namespace App\Actions\Reports;

use App\Models\DailyMetric;
use Illuminate\Support\Facades\DB;

/**
 * Flags a store's pre-aggregated day as stale so the reader serves live data
 * for it and the nightly refresh recomputes it. Fired when a later void or
 * return mutates a day that may already be settled in daily_metrics — a
 * single cheap UPDATE, safe to call from an after-event hook. A no-op when the
 * day was never pre-aggregated (the reader falls back to live anyway).
 */
class MarkDailyMetricsStale
{
    public function __invoke(int $storeId, \DateTimeInterface|string $date): void
    {
        $dateStr = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;

        DB::table('daily_metrics')
            ->where('store_id', $storeId)
            ->where('date', $dateStr)
            ->update([
                'refresh_status' => DailyMetric::STATUS_STALE,
                'updated_at'     => now(),
            ]);
    }
}
