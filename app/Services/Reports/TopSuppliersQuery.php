<?php

namespace App\Services\Reports;

use App\Models\Purchase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query engine for the Top Suppliers report — one row per supplier you
 * purchased from in the period: number of purchases, total purchased, paid,
 * balance owed, and last purchase date, plus their current outstanding
 * balance. Draft and cancelled purchases don't count as spend. Ordered by
 * total purchased.
 */
class TopSuppliersQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function __invoke(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId = null): Collection
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        $rows = DB::table('purchases as p')
            ->join('suppliers as sup', 'sup.id', '=', 'p.supplier_id')
            ->whereNull('p.deleted_at')
            ->whereNotIn('p.status', [Purchase::STATUS_DRAFT, Purchase::STATUS_CANCELLED])
            ->whereBetween('p.purchase_date', [$fromDate, $toDate])
            ->when($storeId, fn ($q) => $q->where('p.store_id', $storeId))
            ->selectRaw('
                p.supplier_id,
                MAX(sup.name)                            AS name,
                MAX(sup.code)                            AS code,
                MAX(sup.outstanding_balance)             AS outstanding,
                COUNT(*)                                 AS purchases_count,
                COALESCE(SUM(p.grand_total), 0)          AS total_purchased,
                COALESCE(SUM(p.paid_total), 0)           AS paid,
                COALESCE(SUM(p.balance_due), 0)          AS balance,
                MAX(p.purchase_date)                     AS last_purchase
            ')
            ->groupBy('p.supplier_id')
            ->orderByDesc('total_purchased')
            ->get();

        return $rows->map(function ($r) {
            return [
                'supplier_id'     => $r->supplier_id,
                'name'            => (string) ($r->name ?? '—'),
                'code'            => $r->code ? (string) $r->code : null,
                'purchases_count' => (int) $r->purchases_count,
                'total_purchased' => number_format((float) $r->total_purchased, 4, '.', ''),
                'paid'            => number_format((float) $r->paid, 4, '.', ''),
                'balance'         => number_format((float) $r->balance, 4, '.', ''),
                'last_purchase'   => (string) $r->last_purchase,
                'outstanding'     => number_format((float) $r->outstanding, 4, '.', ''),
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, string|int>
     */
    public function summarise(Collection $rows): array
    {
        $purchased = $paid = $balance = 0.0;
        foreach ($rows as $r) {
            $purchased += (float) $r['total_purchased'];
            $paid      += (float) $r['paid'];
            $balance   += (float) $r['balance'];
        }

        return [
            'suppliers' => $rows->count(),
            'purchased' => number_format($purchased, 4, '.', ''),
            'paid'      => number_format($paid, 4, '.', ''),
            'balance'   => number_format($balance, 4, '.', ''),
        ];
    }
}
