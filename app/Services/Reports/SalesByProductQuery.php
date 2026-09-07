<?php

namespace App\Services\Reports;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query engine for the Sales by Product report.
 *
 * Groups sale_items by product and returns one row per product with:
 *   product_id, name, sku, qty_sold, revenue, cost, profit, margin_pct
 *
 * `product_name_snapshot` / `sku_snapshot` are used (not the live
 * products table) so renamed or archived products still appear with
 * their historical identity.
 *
 * Ordered by revenue DESC so the best-sellers surface at the top.
 */
class SalesByProductQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function __invoke(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $storeId = null,
    ): Collection {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        $rows = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->whereNull('s.deleted_at')
            ->whereNull('s.voided_at')
            ->where('s.status', Sale::STATUS_COMPLETED)
            ->whereBetween('s.sale_date', [$fromDate, $toDate])
            ->when($storeId, fn ($q) => $q->where('s.store_id', $storeId))
            ->selectRaw('
                si.product_id,
                MAX(si.product_name_snapshot)                     AS name,
                MAX(si.sku_snapshot)                              AS sku,
                SUM(si.quantity)                                  AS qty_sold,
                COALESCE(SUM(si.line_subtotal), 0)                AS revenue,
                COALESCE(SUM(si.quantity * si.unit_cost_snapshot), 0) AS cost
            ')
            ->groupBy('si.product_id')
            ->orderByDesc('revenue')
            ->get();

        return $rows->map(function ($r) {
            $revenue = (float) $r->revenue;
            $cost    = (float) $r->cost;
            $profit  = $revenue - $cost;
            $margin  = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0.0;

            return [
                'product_id' => $r->product_id,
                'name'       => (string) $r->name,
                'sku'        => (string) ($r->sku ?? ''),
                'qty_sold'   => rtrim(rtrim(number_format((float) $r->qty_sold, 4, '.', ''), '0'), '.') ?: '0',
                'revenue'    => number_format($revenue, 4, '.', ''),
                'cost'       => number_format($cost, 4, '.', ''),
                'profit'     => number_format($profit, 4, '.', ''),
                'margin_pct' => $margin,
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, string|int>
     */
    public function summarise(Collection $rows): array
    {
        $revenue = 0.0;
        $cost    = 0.0;
        $profit  = 0.0;
        $qty     = 0.0;

        foreach ($rows as $r) {
            $revenue += (float) $r['revenue'];
            $cost    += (float) $r['cost'];
            $profit  += (float) $r['profit'];
            $qty     += (float) $r['qty_sold'];
        }

        return [
            'products' => $rows->count(),
            'qty_sold' => number_format($qty, 4, '.', ''),
            'revenue'  => number_format($revenue, 4, '.', ''),
            'cost'     => number_format($cost, 4, '.', ''),
            'profit'   => number_format($profit, 4, '.', ''),
            'margin'   => $revenue > 0 ? round((($revenue - $cost) / $revenue) * 100, 1) : 0.0,
        ];
    }
}
