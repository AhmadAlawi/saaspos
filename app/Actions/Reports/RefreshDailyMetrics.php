<?php

namespace App\Actions\Reports;

use App\Models\DailyMetric;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes the pre-aggregated {@see DailyMetric} rows for one store on one
 * day: a per-cashier row for each cashier who traded, plus a store-aggregate
 * row (`cashier_id` = null) covering everything. Idempotent — it deletes the
 * day's rows and rebuilds them inside a transaction, so re-running after a
 * late void/return always lands on the correct numbers.
 *
 * Metric basis mirrors the live reporting surface so the pre-agg and a live
 * query agree: only `status = completed`, non-voided sales count toward gross;
 * COGS/net use the per-line snapshot; returns are the day's completed
 * sale_returns by return_date.
 *
 * Not on the checkout hot path — driven by the nightly command and the
 * shift-close hook. Runs ~6 small grouped queries per (store, day).
 */
class RefreshDailyMetrics
{
    public function __invoke(int $storeId, \DateTimeInterface|string $date): void
    {
        $dateStr = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;
        $now     = now();

        // Per-cashier slices (cashier_id NOT NULL — null-cashier sales fold
        // into the aggregate only, so they never collide with the aggregate
        // row which itself uses cashier_id = null).
        $salesByCashier    = $this->salesAggregates($storeId, $dateStr, groupByCashier: true);
        $itemsByCashier    = $this->itemAggregates($storeId, $dateStr, groupByCashier: true);
        $paymentsByCashier = $this->paymentAggregates($storeId, $dateStr, groupByCashier: true);

        // Store-wide totals (over every sale, including null-cashier ones).
        $salesTotal    = $this->salesAggregates($storeId, $dateStr, groupByCashier: false)->first();
        $itemsTotal    = $this->itemAggregates($storeId, $dateStr, groupByCashier: false)->first();
        $paymentsTotal = $this->paymentAggregates($storeId, $dateStr, groupByCashier: false)->first();
        $returnsTotal  = $this->returnsTotal($storeId, $dateStr);
        $voidedCount   = $this->voidedCount($storeId, $dateStr);

        $rows = [];

        foreach ($salesByCashier as $cashierId => $s) {
            $rows[] = $this->buildRow(
                $storeId, $dateStr, (int) $cashierId, $now,
                $s, $itemsByCashier->get($cashierId), $paymentsByCashier->get($cashierId),
                returnsTotal: '0', voidedCount: 0,
            );
        }

        // The aggregate row always exists (even for a zero-sales day) so the
        // reader can tell "settled, nothing sold" from "not yet computed".
        $rows[] = $this->buildRow(
            $storeId, $dateStr, null, $now,
            $salesTotal, $itemsTotal, $paymentsTotal,
            returnsTotal: $returnsTotal, voidedCount: $voidedCount,
        );

        DB::transaction(function () use ($storeId, $dateStr, $rows) {
            DB::table('daily_metrics')->where('store_id', $storeId)->where('date', $dateStr)->delete();
            DB::table('daily_metrics')->insert($rows);
        });
    }

    /** @return \Illuminate\Support\Collection<int|string, object> keyed by cashier_id when grouped */
    private function salesAggregates(int $storeId, string $date, bool $groupByCashier)
    {
        $q = $this->baseSales($storeId, $date)->selectRaw('
            '.($groupByCashier ? 'cashier_id,' : '').'
            COUNT(*)                          as sales_count,
            COALESCE(SUM(grand_total),    0)  as sales_gross,
            COALESCE(SUM(discount_total), 0)  as discounts_total,
            COALESCE(SUM(tax_total),      0)  as tax_total
        ');

        if ($groupByCashier) {
            return $q->whereNotNull('cashier_id')->groupBy('cashier_id')->get()->keyBy('cashier_id');
        }

        return $q->get();
    }

    /** @return \Illuminate\Support\Collection<int|string, object> */
    private function itemAggregates(int $storeId, string $date, bool $groupByCashier)
    {
        $q = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.voided_at')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->where('sales.store_id', $storeId)
            ->whereDate('sales.sale_date', $date)
            ->selectRaw('
                '.($groupByCashier ? 'sales.cashier_id,' : '').'
                COALESCE(SUM(sale_items.quantity), 0)                                    as items_sold,
                COALESCE(SUM(sale_items.quantity * sale_items.unit_cost_snapshot), 0)    as cogs,
                COALESCE(SUM(sale_items.line_subtotal), 0)                               as revenue_net
            ');

        if ($groupByCashier) {
            return $q->whereNotNull('sales.cashier_id')->groupBy('sales.cashier_id')->get()->keyBy('cashier_id');
        }

        return $q->get();
    }

    /** @return \Illuminate\Support\Collection<int|string, object> */
    private function paymentAggregates(int $storeId, string $date, bool $groupByCashier)
    {
        $q = DB::table('sale_payments as sp')
            ->join('sales as s', 's.id', '=', 'sp.sale_id')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'sp.payment_method_id')
            ->whereNull('s.deleted_at')
            ->whereNull('s.voided_at')
            ->where('s.status', Sale::STATUS_COMPLETED)
            ->where('s.store_id', $storeId)
            ->whereDate('s.sale_date', $date)
            ->whereNull('sp.customer_id') // sale-time tenders only (exclude settlement rows)
            ->selectRaw("
                ".($groupByCashier ? 's.cashier_id,' : '')."
                COALESCE(SUM(CASE WHEN pm.type = 'cash' THEN sp.amount ELSE 0 END), 0) as cash_total,
                COALESCE(SUM(CASE WHEN pm.type = 'card' THEN sp.amount ELSE 0 END), 0) as card_total,
                COALESCE(SUM(CASE WHEN pm.type = 'digital' AND (pm.provider IS NULL OR pm.provider = 'none') THEN sp.amount ELSE 0 END), 0) as upi_total,
                COALESCE(SUM(CASE WHEN pm.provider IS NOT NULL AND pm.provider <> 'none' THEN sp.amount ELSE 0 END), 0) as gateway_total
            ");

        if ($groupByCashier) {
            return $q->whereNotNull('s.cashier_id')->groupBy('s.cashier_id')->get()->keyBy('cashier_id');
        }

        return $q->get();
    }

    private function returnsTotal(int $storeId, string $date): string
    {
        return (string) DB::table('sale_returns')
            ->whereNull('deleted_at')
            ->where('status', SaleReturn::STATUS_COMPLETED)
            ->where('store_id', $storeId)
            ->whereDate('return_date', $date)
            ->sum('grand_total');
    }

    private function voidedCount(int $storeId, string $date): int
    {
        return (int) DB::table('sales')
            ->where('store_id', $storeId)
            ->where('status', Sale::STATUS_VOIDED)
            ->whereDate('voided_at', $date)
            ->count();
    }

    private function baseSales(int $storeId, string $date)
    {
        // whereDate (not raw equality) so a date column that carries a time
        // component under SQLite still matches; under MySQL it's a DATE column
        // and this is a no-op wrapper.
        return DB::table('sales')
            ->whereNull('deleted_at')
            ->whereNull('voided_at')
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('store_id', $storeId)
            ->whereDate('sale_date', $date);
    }

    /**
     * Assemble one daily_metrics insert row from the aggregate objects.
     *
     * @param  object|null  $sales     {sales_count, sales_gross, discounts_total, tax_total}
     * @param  object|null  $items     {items_sold, cogs, revenue_net}
     * @param  object|null  $payments  {cash_total, card_total, upi_total, gateway_total}
     * @return array<string, mixed>
     */
    private function buildRow(int $storeId, string $date, ?int $cashierId, $now, $sales, $items, $payments, string $returnsTotal, int $voidedCount): array
    {
        $count      = (int) ($sales->sales_count ?? 0);
        $gross      = (string) ($sales->sales_gross ?? 0);
        $revenueNet = (string) ($items->revenue_net ?? 0);
        $cogs       = (string) ($items->cogs ?? 0);
        $net        = bcsub($gross, $returnsTotal, 4);
        $grossProfit = bcsub($revenueNet, $cogs, 4);
        $avgBasket  = $count > 0 ? bcdiv($gross, (string) $count, 4) : '0';

        return [
            'store_id'              => $storeId,
            'date'                  => $date,
            'cashier_id'            => $cashierId,
            'sales_count'           => $count,
            'sales_gross'           => $gross,
            'sales_returns'         => $returnsTotal,
            'sales_net'             => $net,
            'tax_total'             => (string) ($sales->tax_total ?? 0),
            'cogs'                  => $cogs,
            'gross_profit'          => $grossProfit,
            'cash_total'            => (string) ($payments->cash_total ?? 0),
            'card_total'            => (string) ($payments->card_total ?? 0),
            'upi_total'             => (string) ($payments->upi_total ?? 0),
            'gateway_total'         => (string) ($payments->gateway_total ?? 0),
            'store_credit_total'    => 0,     // best-effort deferred — no reader yet
            'customer_credit_total' => 0,     // "
            'discounts_total'       => (string) ($sales->discounts_total ?? 0),
            'transactions_voided'   => $voidedCount,
            'avg_basket_size'       => $avgBasket,
            'items_sold'            => (string) ($items->items_sold ?? 0),
            'new_customers_acquired' => 0,    // "
            'refresh_status'        => DailyMetric::STATUS_CURRENT,
            'created_at'            => $now,
            'updated_at'            => $now,
        ];
    }
}
