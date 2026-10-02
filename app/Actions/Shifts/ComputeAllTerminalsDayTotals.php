<?php

namespace App\Actions\Shifts;

use App\Models\TradingDay;
use Carbon\CarbonInterface;

/**
 * One-click "all terminals" rollup for a store's business date — every
 * {@see TradingDay} that date (one per terminal, see TradingDay's own
 * docblock) described individually, then a grand total across all of
 * them. {@see ComputeTradingDayTotals} already does the per-terminal
 * math; this just runs it once per TradingDay and sums the results,
 * same non-re-query-of-sales approach (each shift already reconciled
 * its own cash drawer at close).
 *
 * A terminal with no TradingDay yet for the date (never opened) simply
 * doesn't appear — nothing to report.
 *
 * Returned shape:
 *   [
 *     'business_date' => '2026-09-04',
 *     'terminals' => [
 *       ['trading_day_id','terminal_name','status','totals' => <ComputeTradingDayTotals shape>],
 *       …
 *     ],
 *     'grand' => <same shape as one terminal's totals, summed across all>,
 *   ]
 */
class ComputeAllTerminalsDayTotals
{
    public function __construct(
        private readonly ComputeTradingDayTotals $computeDay,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(int $storeId, CarbonInterface $businessDate): array
    {
        $days = TradingDay::query()
            ->where('store_id', $storeId)
            ->where('business_date', $businessDate->toDateString())
            ->with('terminal:id,name')
            ->orderBy('terminal_id')
            ->get();

        $terminals = [];
        $salesCount = 0;
        $salesTotal = '0';
        $refundsCount = 0;
        $refundsTotal = '0';
        $openingCash = '0';
        $closingCash = '0';
        $varianceTotal = '0';
        $varianceOverTotal  = '0';
        $varianceShortTotal = '0';
        $discountTotal = '0';
        $payOutsTotal  = '0';
        $shiftCount = 0;
        $paymentByMethod = []; // method_id => row

        foreach ($days as $day) {
            $totals = ($this->computeDay)($day);

            $terminals[] = [
                'trading_day_id' => $day->id,
                'terminal_name'  => $day->terminal?->name ?? __('shifts.day.no_terminal'),
                'status'         => $day->status,
                'totals'         => $totals,
            ];

            $shiftCount   += (int) $totals['shift_count'];
            $salesCount   += (int) $totals['sales_count'];
            $salesTotal    = bcadd($salesTotal, (string) $totals['sales_total'], 4);
            $refundsCount += (int) $totals['refunds_count'];
            $refundsTotal  = bcadd($refundsTotal, (string) $totals['refunds_total'], 4);
            // Opening cash is a per-till float, not additive across
            // terminals in any meaningful "total drawer" sense, but
            // summing it still answers "how much float did we start
            // the day with, all tills combined" — the only reading
            // that makes sense at the all-terminals level.
            $openingCash   = bcadd($openingCash, (string) $totals['opening_cash'], 4);
            $closingCash   = bcadd($closingCash, (string) $totals['closing_cash_total'], 4);
            $varianceTotal = bcadd($varianceTotal, (string) $totals['cash_variance_total'], 4);
            $varianceOverTotal  = bcadd($varianceOverTotal, (string) $totals['variance_over_total'], 4);
            $varianceShortTotal = bcadd($varianceShortTotal, (string) $totals['variance_short_total'], 4);
            $discountTotal = bcadd($discountTotal, (string) $totals['discount_total'], 4);
            $payOutsTotal  = bcadd($payOutsTotal, (string) $totals['pay_outs_total'], 4);

            foreach ($totals['payment_totals'] as $row) {
                $id = (int) ($row['method_id'] ?? 0);
                if (! isset($paymentByMethod[$id])) {
                    $paymentByMethod[$id] = $row;
                    $paymentByMethod[$id]['amount'] = (string) ($row['amount'] ?? '0');
                } else {
                    $paymentByMethod[$id]['amount'] = bcadd($paymentByMethod[$id]['amount'], (string) ($row['amount'] ?? '0'), 4);
                }
            }
        }

        return [
            'business_date' => $businessDate->toDateString(),
            'terminals'      => $terminals,
            'grand'          => [
                'terminal_count'      => count($terminals),
                'shift_count'         => $shiftCount,
                'sales_count'         => $salesCount,
                'sales_total'         => $salesTotal,
                'refunds_count'       => $refundsCount,
                'refunds_total'       => $refundsTotal,
                'opening_cash'        => $openingCash,
                'closing_cash_total'  => $closingCash,
                'cash_variance_total' => $varianceTotal,
                'variance_over_total'  => $varianceOverTotal,
                'variance_short_total' => $varianceShortTotal,
                'discount_total'      => $discountTotal,
                'pay_outs_total'      => $payOutsTotal,
                'payment_totals'      => array_values($paymentByMethod),
            ],
        ];
    }
}
