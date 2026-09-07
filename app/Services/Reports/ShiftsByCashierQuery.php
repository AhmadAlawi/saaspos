<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query engine for the Shifts by Cashier report — one row per cashier over
 * the period: number of closed shifts, total hours worked, total sales rung
 * up, average shift duration, and cumulative cash variance (the audit
 * signal). Only closed shifts count, so hours and variance are meaningful.
 *
 * Shift duration is summed in PHP rather than SQL so the report stays
 * database-agnostic (MySQL TIMESTAMPDIFF vs SQLite julianday differ).
 * Ordered by total sales.
 */
class ShiftsByCashierQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function __invoke(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId = null): Collection
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        $shifts = DB::table('shifts as sh')
            ->leftJoin('users as u', 'u.id', '=', 'sh.user_id')
            ->whereNotNull('sh.closed_at')
            ->whereBetween('sh.opened_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($storeId, fn ($q) => $q->where('sh.store_id', $storeId))
            ->select([
                'sh.user_id',
                'u.name as name',
                'sh.opened_at',
                'sh.closed_at',
                'sh.sales_total',
                'sh.cash_variance',
            ])
            ->get();

        return $shifts
            ->groupBy('user_id')
            ->map(function (Collection $rows) {
                $userId   = $rows->first()->user_id;
                $name     = $rows->first()->name;
                $count    = $rows->count();
                $hours    = 0.0;
                $sales    = 0.0;
                $variance = 0.0;

                foreach ($rows as $r) {
                    $opened = CarbonImmutable::parse($r->opened_at);
                    $closed = CarbonImmutable::parse($r->closed_at);
                    $hours += abs($closed->diffInSeconds($opened)) / 3600;
                    $sales += (float) $r->sales_total;
                    $variance += (float) $r->cash_variance;
                }

                return [
                    'user_id'        => $userId,
                    'name'           => (string) ($name ?? '—'),
                    'shifts_count'   => $count,
                    'total_hours'    => round($hours, 1),
                    'total_sales'    => number_format($sales, 4, '.', ''),
                    'avg_duration'   => round($count > 0 ? $hours / $count : 0, 1),
                    'total_variance' => number_format($variance, 4, '.', ''),
                ];
            })
            ->sortByDesc(fn ($r) => (float) $r['total_sales'])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, string|int|float>
     */
    public function summarise(Collection $rows): array
    {
        $shifts = 0;
        $hours = $sales = $variance = 0.0;
        foreach ($rows as $r) {
            $shifts   += (int) $r['shifts_count'];
            $hours    += (float) $r['total_hours'];
            $sales    += (float) $r['total_sales'];
            $variance += (float) $r['total_variance'];
        }

        return [
            'cashiers'       => $rows->count(),
            'shifts_count'   => $shifts,
            'total_hours'    => round($hours, 1),
            'total_sales'    => number_format($sales, 4, '.', ''),
            'total_variance' => number_format($variance, 4, '.', ''),
        ];
    }
}
