<?php

namespace App\Actions\Shifts;

use App\Models\TradingDay;

/**
 * Rolls up a {@see TradingDay}'s totals from its already-closed child
 * {@see \App\Models\Shift} rows — NOT a re-query of `sales`/`sale_returns`.
 * Each shift already reconciled its own cash drawer independently at
 * close (see {@see CloseShift}/{@see ComputeShiftTotals}); this just
 * sums what's already been recorded, plus a per-employee breakdown so
 * the combined report still shows who rang up what.
 *
 * Returned shape:
 *   [
 *     'shift_count'       => 3,
 *     'sales_count'       => 96,
 *     'sales_total'       => '24850.0000',
 *     'refunds_count'     => 4,
 *     'refunds_total'     => '340.0000',
 *     'opening_cash'      => '1000.0000',   // first shift of the day
 *     'closing_cash_total'=> '8420.0000',   // sum of every shift's counted cash
 *     'cash_variance_total' => '-5.0000',
 *     'payment_totals'    => [ ['method_id','code','name','type','amount'], … ],
 *     'employees'         => [
 *       ['shift_id','cashier_name','opened_at','closed_at','status','sales_total','refunds_total','cash_variance'],
 *       …
 *     ],
 *   ]
 */
class ComputeTradingDayTotals
{
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
        $paymentByMethod = []; // method_id => row
        $employees = [];

        foreach ($shifts as $shift) {
            $salesCount   += (int) $shift->sales_count;
            $salesTotal    = bcadd($salesTotal, (string) $shift->sales_total, 4);
            $refundsCount += (int) $shift->refunds_count;
            $refundsTotal  = bcadd($refundsTotal, (string) $shift->refunds_total, 4);
            $closingCash   = bcadd($closingCash, (string) ($shift->closing_cash_counted ?? '0'), 4);
            $varianceTotal = bcadd($varianceTotal, (string) ($shift->cash_variance ?? '0'), 4);

            foreach ((array) ($shift->payment_totals ?? []) as $row) {
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
                'sales_total'   => (string) $shift->sales_total,
                'refunds_total' => (string) $shift->refunds_total,
                'cash_variance' => (string) ($shift->cash_variance ?? '0'),
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
            'payment_totals'      => array_values($paymentByMethod),
            'employees'           => $employees,
        ];
    }
}
