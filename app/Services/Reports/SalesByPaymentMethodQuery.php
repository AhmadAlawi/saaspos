<?php

namespace App\Services\Reports;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query engine for the Sales by Payment Method report — how much was
 * taken through each tender (cash, card, UPI, each gateway) over the
 * period. The reconciliation view: one row per method with the number of
 * sales it appears on and the total amount received.
 *
 * Split-tender sales appear under each method they used, so `sales_count`
 * is COUNT(DISTINCT sale) per method (not additive across rows).
 */
class SalesByPaymentMethodQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function __invoke(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId = null): Collection
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        $rows = DB::table('sale_payments as sp')
            ->join('sales as s', 's.id', '=', 'sp.sale_id')
            ->join('payment_methods as pm', 'pm.id', '=', 'sp.payment_method_id')
            ->whereNull('s.deleted_at')
            ->whereNull('s.voided_at')
            ->where('s.status', Sale::STATUS_COMPLETED)
            ->whereBetween('s.sale_date', [$fromDate, $toDate])
            ->when($storeId, fn ($q) => $q->where('s.store_id', $storeId))
            ->selectRaw('
                pm.id                              AS method_id,
                MAX(pm.name)                       AS name,
                MAX(pm.type)                       AS type,
                COUNT(DISTINCT sp.sale_id)         AS sales_count,
                COALESCE(SUM(sp.amount), 0)        AS total
            ')
            ->groupBy('pm.id')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($r) => [
            'method_id'  => $r->method_id,
            'name'       => (string) $r->name,
            'type'       => (string) $r->type,
            'sales_count'=> (int) $r->sales_count,
            'total'      => number_format((float) $r->total, 4, '.', ''),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, string|int>
     */
    public function summarise(Collection $rows): array
    {
        $total = 0.0;
        foreach ($rows as $r) {
            $total += (float) $r['total'];
        }

        return [
            'methods' => $rows->count(),
            'total'   => number_format($total, 4, '.', ''),
        ];
    }
}
