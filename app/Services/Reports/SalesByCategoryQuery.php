<?php

namespace App\Services\Reports;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query engine for the Sales by Category report — quantity sold, revenue,
 * cost, and margin grouped by the product's current category. Uncategorised
 * products roll into a single "Uncategorised" bucket.
 *
 * Uses the live `products.category_id` (categories aren't snapshotted per
 * line), so a product re-categorised after a sale reports under its
 * current category — the intended behaviour for "how is category X doing".
 */
class SalesByCategoryQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function __invoke(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId = null): Collection
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        $rows = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->leftJoin('products as p', 'p.id', '=', 'si.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->whereNull('s.deleted_at')
            ->whereNull('s.voided_at')
            ->where('s.status', Sale::STATUS_COMPLETED)
            ->whereBetween('s.sale_date', [$fromDate, $toDate])
            ->when($storeId, fn ($q) => $q->where('s.store_id', $storeId))
            ->selectRaw('
                c.id                                                 AS category_id,
                MAX(c.name)                                          AS name,
                SUM(si.quantity)                                     AS qty_sold,
                COALESCE(SUM(si.line_subtotal), 0)                   AS revenue,
                COALESCE(SUM(si.quantity * si.unit_cost_snapshot), 0) AS cost
            ')
            ->groupBy('c.id')
            ->orderByDesc('revenue')
            ->get();

        return $rows->map(function ($r) {
            $revenue = (float) $r->revenue;
            $cost    = (float) $r->cost;
            $profit  = $revenue - $cost;
            $margin  = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0.0;

            return [
                'category_id' => $r->category_id,
                'name'        => (string) ($r->name ?? __('reports.sales_by_category.uncategorised')),
                'qty_sold'    => rtrim(rtrim(number_format((float) $r->qty_sold, 4, '.', ''), '0'), '.') ?: '0',
                'revenue'     => number_format($revenue, 4, '.', ''),
                'cost'        => number_format($cost, 4, '.', ''),
                'profit'      => number_format($profit, 4, '.', ''),
                'margin_pct'  => $margin,
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, string|int>
     */
    public function summarise(Collection $rows): array
    {
        $revenue = $cost = $profit = 0.0;
        foreach ($rows as $r) {
            $revenue += (float) $r['revenue'];
            $cost    += (float) $r['cost'];
            $profit  += (float) $r['profit'];
        }

        return [
            'categories' => $rows->count(),
            'revenue'    => number_format($revenue, 4, '.', ''),
            'profit'     => number_format($profit, 4, '.', ''),
            'margin'     => $revenue > 0 ? round((($revenue - $cost) / $revenue) * 100, 1) : 0.0,
        ];
    }
}
