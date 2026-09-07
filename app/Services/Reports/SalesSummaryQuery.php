<?php

namespace App\Services\Reports;

use App\Models\Sale;
use App\Models\SaleReturn;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query engine for the Sales Summary report.
 *
 * Returns:
 *  - `kpis`       : revenue, transactions, avg_ticket, discount, tax, refunds
 *  - `by_day`     : Collection of daily rows (date, transactions, subtotal,
 *                   discount, tax, grand_total) — ordered by date ASC
 *  - `by_payment` : Collection of payment-method rows (method_name, total,
 *                   sale_count) — ordered by total DESC
 *
 * Only completed + non-voided sales are counted. Sale-time tenders
 * (sale_payments.customer_id IS NULL) drive the payment-method breakdown;
 * settlement rows from customer-payment flows are excluded so the totals
 * match the sales side.
 */
class SalesSummaryQuery
{
    public function __invoke(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $storeId = null,
    ): array {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // ── KPI aggregates ────────────────────────────────────────────
        $agg = DB::table('sales')
            ->whereNull('deleted_at')
            ->whereNull('voided_at')
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sale_date', [$fromDate, $toDate])
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('
                COUNT(*)                          AS transactions,
                COALESCE(SUM(grand_total),    0)  AS revenue,
                COALESCE(SUM(discount_total), 0)  AS discount,
                COALESCE(SUM(tax_total),      0)  AS tax
            ')
            ->first();

        $revenue      = (string) ($agg->revenue      ?? '0');
        $transactions = (int)    ($agg->transactions  ?? 0);
        $discount     = (string) ($agg->discount      ?? '0');
        $tax          = (string) ($agg->tax           ?? '0');
        $avgTicket    = $transactions > 0
            ? bcdiv($revenue, (string) $transactions, 4)
            : '0.0000';

        // Refunds = completed sale_returns in the period.
        $refundTotal = (string) DB::table('sale_returns')
            ->whereNull('deleted_at')
            ->where('status', SaleReturn::STATUS_COMPLETED)
            ->whereBetween('return_date', [$fromDate, $toDate])
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->sum('grand_total');

        // ── Daily breakdown ───────────────────────────────────────────
        $byDay = DB::table('sales')
            ->whereNull('deleted_at')
            ->whereNull('voided_at')
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sale_date', [$fromDate, $toDate])
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('
                DATE(sale_date)                       AS day,
                COUNT(*)                              AS transactions,
                COALESCE(SUM(subtotal),       0)      AS subtotal,
                COALESCE(SUM(discount_total), 0)      AS discount,
                COALESCE(SUM(tax_total),      0)      AS tax,
                COALESCE(SUM(grand_total),    0)      AS grand_total
            ')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // ── Payment method breakdown ──────────────────────────────────
        $byPayment = DB::table('sale_payments as sp')
            ->join('sales as s', 's.id', '=', 'sp.sale_id')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'sp.payment_method_id')
            ->whereNull('s.deleted_at')
            ->whereNull('s.voided_at')
            ->where('s.status', Sale::STATUS_COMPLETED)
            ->whereBetween('s.sale_date', [$fromDate, $toDate])
            ->when($storeId, fn ($q) => $q->where('s.store_id', $storeId))
            ->whereNull('sp.customer_id')  // sale-time tenders only
            ->selectRaw('
                COALESCE(pm.name, "Other")    AS method_name,
                COALESCE(SUM(sp.amount), 0)   AS total,
                COUNT(DISTINCT sp.sale_id)    AS sale_count
            ')
            ->groupBy('sp.payment_method_id', 'pm.name')
            ->orderByDesc('total')
            ->get();

        return [
            'kpis' => [
                'revenue'      => $revenue,
                'transactions' => $transactions,
                'avg_ticket'   => $avgTicket,
                'discount'     => $discount,
                'tax'          => $tax,
                'refunds'      => $refundTotal,
            ],
            'by_day'     => $byDay,
            'by_payment' => $byPayment,
            'totals'     => [
                'subtotal'    => (string) $byDay->sum('subtotal'),
                'discount'    => (string) $byDay->sum('discount'),
                'tax'         => (string) $byDay->sum('tax'),
                'grand_total' => $revenue,
            ],
        ];
    }
}
