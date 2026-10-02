<?php

namespace App\Actions\Shifts;

use App\Models\TradingDay;

/**
 * Rolls up a {@see TradingDay}'s totals from its child {@see \App\Models\Shift}
 * rows, plus a per-employee breakdown so the combined report still
 * shows who rang up what.
 *
 * A CLOSED shift's `sales_total`/`refunds_total`/`payment_totals`/
 * `closing_cash_counted`/`cash_variance` columns are frozen at close
 * (see {@see CloseShift}) and read as-is — no re-query of `sales`/
 * `sale_returns`. An OPEN shift has none of that written yet (those
 * columns default to zero until close), so it falls back to
 * {@see ComputeShiftTotals} — the same live X-report math — for
 * sales/refunds/payments; its "closing cash" reads as `expected_cash`
 * (nothing's been counted yet) and its variance reads as zero (unknown
 * until it's actually closed). Without this branch, a day rollup taken
 * before every terminal closes for the night would show zeros for
 * every still-open till.
 *
 * Returned shape:
 *   [
 *     'shift_count'       => 3,
 *     'sales_count'       => 96,
 *     'sales_total'       => '24850.0000',
 *     'refunds_count'     => 4,
 *     'refunds_total'     => '340.0000',
 *     'opening_cash'      => '1000.0000',   // first shift of the day
 *     'closing_cash_total'=> '8420.0000',   // sum of every shift's counted (or expected, if still open) cash
 *     'cash_variance_total' => '-5.0000',
 *     'variance_over_total'  => '3.0000',   // sum of every shift that came up heavy
 *     'variance_short_total' => '8.0000',   // sum of every shift that came up light (net = cash_variance_total)
 *     'discount_total'    => '150.0000',
 *     'pay_outs_total'    => '75.0000',
 *     'payment_totals'    => [ ['method_id','code','name','type','amount'], … ],
 *     'employees'         => [
 *       ['shift_id','cashier_name','opened_at','closed_at','status','sales_total','refunds_total','cash_variance'],
 *       …
 *     ],
 *   ]
 */
class ComputeTradingDayTotals
{
    public function __construct(
        private readonly ComputeShiftTotals $computeShift,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(TradingDay $day): array
    {
        $shifts = $day->shifts()->with('cashier:id,name')->orderBy('opened_at')->get();

        $salesCount   = 0;
        $salesTotal   = '0';
        $refundsCount = 0;
        $refundsTotal = '0';
        $closingCash  = '0';
        $varianceTotal = '0';
        $varianceOverTotal  = '0';
        $varianceShortTotal = '0';
        $discountTotal = '0';
        $payOutsTotal  = '0';
        $paymentByMethod = []; // method_id => row
        $employees = [];

        foreach ($shifts as $shift) {
            if ($shift->isOpen()) {
                $live = ($this->computeShift)($shift);
                $shiftSalesTotal   = $live['sales_total'];
                $shiftRefundsTotal = $live['refunds_total'];
                $shiftPaymentTotals = $live['payment_totals'];
                $shiftClosingCash  = $live['expected_cash'];
                $shiftVariance     = '0.0000';
                $shiftSalesCount   = $live['sales_count'];
                $shiftRefundsCount = $live['refunds_count'];
                $shiftDiscountTotal = $live['discount_total'];
                $shiftPayOuts       = $live['pay_outs'];
            } else {
                $shiftSalesTotal   = (string) $shift->sales_total;
                $shiftRefundsTotal = (string) $shift->refunds_total;
                $shiftPaymentTotals = (array) ($shift->payment_totals ?? []);
                $shiftClosingCash  = (string) ($shift->closing_cash_counted ?? '0');
                $shiftVariance     = (string) ($shift->cash_variance ?? '0');
                $shiftSalesCount   = (int) $shift->sales_count;
                $shiftRefundsCount = (int) $shift->refunds_count;
                // Only available for shifts closed after `frozen_totals`
                // shipped (see CloseShift) — older closed shifts have no
                // per-shift discount/pay-outs snapshot to read, so they
                // contribute 0 here rather than a live recompute (which
                // would reintroduce the exact post-close drift
                // `frozen_totals` exists to prevent).
                $shiftDiscountTotal = (string) ($shift->frozen_totals['discount_total'] ?? '0');
                $shiftPayOuts       = (string) ($shift->frozen_totals['pay_outs'] ?? '0');
            }

            $salesCount   += $shiftSalesCount;
            $salesTotal    = bcadd($salesTotal, $shiftSalesTotal, 4);
            $refundsCount += $shiftRefundsCount;
            $refundsTotal  = bcadd($refundsTotal, $shiftRefundsTotal, 4);
            $closingCash   = bcadd($closingCash, $shiftClosingCash, 4);
            $varianceTotal = bcadd($varianceTotal, $shiftVariance, 4);
            $discountTotal = bcadd($discountTotal, $shiftDiscountTotal, 4);
            $payOutsTotal  = bcadd($payOutsTotal, $shiftPayOuts, 4);
            // Split into "short" (till came up light) and "over" (till
            // came up heavy) so one cancels the other out in neither
            // direction — netting them into a single figure hid real
            // shortages behind an unrelated terminal's overage.
            if (bccomp($shiftVariance, '0', 4) < 0) {
                $varianceShortTotal = bcadd($varianceShortTotal, ltrim($shiftVariance, '-'), 4);
            } elseif (bccomp($shiftVariance, '0', 4) > 0) {
                $varianceOverTotal = bcadd($varianceOverTotal, $shiftVariance, 4);
            }

            foreach ($shiftPaymentTotals as $row) {
                $id = (int) ($row['method_id'] ?? 0);
                if (! isset($paymentByMethod[$id])) {
                    $paymentByMethod[$id] = $row;
                    $paymentByMethod[$id]['amount'] = (string) ($row['amount'] ?? '0');
                } else {
                    $paymentByMethod[$id]['amount'] = bcadd($paymentByMethod[$id]['amount'], (string) ($row['amount'] ?? '0'), 4);
                }
            }

            $employees[] = [
                'shift_id'      => $shift->id,
                'cashier_name'  => $shift->cashier?->name ?? '—',
                'opened_at'     => optional($shift->opened_at)->toIso8601String(),
                'closed_at'     => optional($shift->closed_at)->toIso8601String(),
                'status'        => $shift->status,
                'sales_total'   => $shiftSalesTotal,
                'refunds_total' => $shiftRefundsTotal,
                'cash_variance' => $shiftVariance,
            ];
        }

        return [
            'shift_count'         => $shifts->count(),
            'sales_count'         => $salesCount,
            'sales_total'         => $salesTotal,
            'refunds_count'       => $refundsCount,
            'refunds_total'       => $refundsTotal,
            'opening_cash'        => (string) ($shifts->first()?->opening_cash ?? '0'),
            'closing_cash_total'  => $closingCash,
            'cash_variance_total' => $varianceTotal,
            'variance_over_total'  => $varianceOverTotal,
            'variance_short_total' => $varianceShortTotal,
            'discount_total'      => $discountTotal,
            'pay_outs_total'      => $payOutsTotal,
            'payment_totals'      => array_values($paymentByMethod),
            'employees'           => $employees,
        ];
    }
}
