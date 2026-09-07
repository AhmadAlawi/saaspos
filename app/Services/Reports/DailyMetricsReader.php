<?php

namespace App\Services\Reports;

use App\Models\DailyMetric;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reads a daily revenue + transaction series for a store, transparently using
 * the pre-aggregated {@see DailyMetric} store rows for *settled* days (any day
 * before today with a `current` aggregate row) and a single live query for the
 * rest (today, or days that are missing / stale). Falls back to fully-live
 * automatically when nothing has been pre-aggregated yet, so it's always
 * correct — just faster once the nightly refresh has run (docs §13.3).
 */
class DailyMetricsReader
{
    /**
     * @return array<string, array{sales_gross: float, sales_count: int}> keyed by 'Y-m-d'
     */
    public function dailySeries(int $storeId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $today   = CarbonImmutable::now()->toDateString();
        $fromStr = $from->toDateString();
        $toStr   = $to->toDateString();

        // Pre-aggregated store rows (aggregate = cashier_id null, current only).
        $agg = DB::table('daily_metrics')
            ->where('store_id', $storeId)
            ->whereNull('cashier_id')
            ->where('refresh_status', DailyMetric::STATUS_CURRENT)
            ->whereBetween('date', [$fromStr, $toStr])
            ->get(['date', 'sales_gross', 'sales_count'])
            ->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());

        $out       = [];
        $liveDates = [];

        for ($cursor = $from->startOfDay(); $cursor->lessThanOrEqualTo($to->startOfDay()); $cursor = $cursor->addDay()) {
            $d       = $cursor->toDateString();
            $settled = $d < $today; // string compare of Y-m-d is chronological

            if ($settled && isset($agg[$d])) {
                $out[$d] = [
                    'sales_gross' => (float) $agg[$d]->sales_gross,
                    'sales_count' => (int) $agg[$d]->sales_count,
                ];
            } else {
                $liveDates[] = $d;
                $out[$d]     = ['sales_gross' => 0.0, 'sales_count' => 0];
            }
        }

        if ($liveDates !== []) {
            // DATE(sale_date) normalises away any time component (SQLite keeps
            // one on a date-cast column; MySQL's DATE column already lacks it)
            // so the whereIn / group-by match the plain 'Y-m-d' liveDates.
            $live = DB::table('sales')
                ->whereNull('deleted_at')
                ->whereNull('voided_at')
                ->where('status', Sale::STATUS_COMPLETED)
                ->where('store_id', $storeId)
                ->whereIn(DB::raw('DATE(sale_date)'), $liveDates)
                ->groupBy(DB::raw('DATE(sale_date)'))
                ->selectRaw('DATE(sale_date) as d, COUNT(*) as sales_count, COALESCE(SUM(grand_total), 0) as sales_gross')
                ->get();

            foreach ($live as $r) {
                $d       = Carbon::parse($r->d)->toDateString();
                $out[$d] = [
                    'sales_gross' => (float) $r->sales_gross,
                    'sales_count' => (int) $r->sales_count,
                ];
            }
        }

        return $out;
    }
}
