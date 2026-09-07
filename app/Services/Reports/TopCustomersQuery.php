<?php

namespace App\Services\Reports;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query engine for the Top Customers report — one row per customer who
 * bought in the period: visits (transaction count), total spent, average
 * basket, and last visit, plus their current outstanding balance. Walk-in
 * (customer-less) sales are excluded — this is the "who are my best
 * customers" view. Ordered by total spent.
 */
class TopCustomersQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function __invoke(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId = null): Collection
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        $rows = DB::table('sales as s')
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('customer_groups as g', 'g.id', '=', 'c.customer_group_id')
            ->whereNull('s.deleted_at')
            ->whereNull('s.voided_at')
            ->whereNotNull('s.customer_id')
            ->where('s.status', Sale::STATUS_COMPLETED)
            ->whereBetween('s.sale_date', [$fromDate, $toDate])
            ->when($storeId, fn ($q) => $q->where('s.store_id', $storeId))
            ->selectRaw('
                s.customer_id,
                MAX(c.name)                              AS name,
                MAX(c.code)                              AS code,
                MAX(g.name)                              AS group_name,
                MAX(c.outstanding_balance)               AS outstanding,
                COUNT(*)                                 AS visits,
                COALESCE(SUM(s.grand_total), 0)          AS total_spent,
                MAX(s.sale_date)                         AS last_visit
            ')
            ->groupBy('s.customer_id')
            ->orderByDesc('total_spent')
            ->get();

        return $rows->map(function ($r) {
            $visits = (int) $r->visits;
            $spent  = (float) $r->total_spent;

            return [
                'customer_id' => $r->customer_id,
                'name'        => (string) ($r->name ?? '—'),
                'code'        => $r->code ? (string) $r->code : null,
                'group_name'  => $r->group_name ? (string) $r->group_name : null,
                'visits'      => $visits,
                'total_spent' => number_format($spent, 4, '.', ''),
                'avg_basket'  => number_format($visits > 0 ? $spent / $visits : 0, 4, '.', ''),
                'last_visit'  => (string) $r->last_visit,
                'outstanding' => number_format((float) $r->outstanding, 4, '.', ''),
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, string|int>
     */
    public function summarise(Collection $rows): array
    {
        $visits = $spent = $outstanding = 0.0;
        foreach ($rows as $r) {
            $visits      += (float) $r['visits'];
            $spent       += (float) $r['total_spent'];
            $outstanding += (float) $r['outstanding'];
        }

        return [
            'customers'   => $rows->count(),
            'revenue'     => number_format($spent, 4, '.', ''),
            'avg_basket'  => number_format($visits > 0 ? $spent / $visits : 0, 4, '.', ''),
            'outstanding' => number_format($outstanding, 4, '.', ''),
        ];
    }
}
