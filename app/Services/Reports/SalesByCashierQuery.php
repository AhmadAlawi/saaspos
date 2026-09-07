<?php

namespace App\Services\Reports;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query engine for the Sales by Cashier report — one row per cashier with
 * their transaction count, revenue, discounts given, items sold, and
 * average basket over the period. Surfaces who's selling and who's
 * discounting heavily.
 */
class SalesByCashierQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function __invoke(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId = null): Collection
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        $sales = DB::table('sales as s')
            ->leftJoin('users as u', 'u.id', '=', 's.cashier_id')
            ->whereNull('s.deleted_at')
            ->whereNull('s.voided_at')
            ->where('s.status', Sale::STATUS_COMPLETED)
            ->whereBetween('s.sale_date', [$fromDate, $toDate])
            ->when($storeId, fn ($q) => $q->where('s.store_id', $storeId))
            ->selectRaw('
                s.cashier_id,
                MAX(u.name)                              AS name,
                COUNT(*)                                 AS sales_count,
                COALESCE(SUM(s.grand_total), 0)          AS revenue,
                COALESCE(SUM(s.discount_total), 0)       AS discounts
            ')
            ->groupBy('s.cashier_id')
            ->orderByDesc('revenue')
            ->get();

        // Items sold per cashier — separate pass so the revenue SUM above
        // isn't multiplied by the item join.
        $itemsByCashier = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->whereNull('s.deleted_at')
            ->whereNull('s.voided_at')
            ->where('s.status', Sale::STATUS_COMPLETED)
            ->whereBetween('s.sale_date', [$fromDate, $toDate])
            ->when($storeId, fn ($q) => $q->where('s.store_id', $storeId))
            ->selectRaw('s.cashier_id, COALESCE(SUM(si.quantity), 0) AS items_sold')
            ->groupBy('s.cashier_id')
            ->pluck('items_sold', 'cashier_id');

        return $sales->map(function ($r) use ($itemsByCashier) {
            $count   = (int) $r->sales_count;
            $revenue = (float) $r->revenue;

            return [
                'cashier_id' => $r->cashier_id,
                'name'       => (string) ($r->name ?? '—'),
                'sales_count'=> $count,
                'items_sold' => rtrim(rtrim(number_format((float) ($itemsByCashier[$r->cashier_id] ?? 0), 4, '.', ''), '0'), '.') ?: '0',
                'revenue'    => number_format($revenue, 4, '.', ''),
                'discounts'  => number_format((float) $r->discounts, 4, '.', ''),
                'avg_basket' => number_format($count > 0 ? $revenue / $count : 0, 4, '.', ''),
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, string|int>
     */
    public function summarise(Collection $rows): array
    {
        $count = $revenue = $discounts = 0.0;
        foreach ($rows as $r) {
            $count     += (float) $r['sales_count'];
            $revenue   += (float) $r['revenue'];
            $discounts += (float) $r['discounts'];
        }

        return [
            'cashiers'   => $rows->count(),
            'sales_count'=> (int) $count,
            'revenue'    => number_format($revenue, 4, '.', ''),
            'discounts'  => number_format($discounts, 4, '.', ''),
            'avg_basket' => number_format($count > 0 ? $revenue / $count : 0, 4, '.', ''),
        ];
    }
}
