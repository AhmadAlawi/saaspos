<?php

namespace App\Services\Reports;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pure query + bucket math for the Aged Receivables report.
 *
 * Inputs:
 *   - `asOf` date — the snapshot date the report is computed against
 *     (defaults to today). Sale ages count days from `sale_date` to
 *     `asOf`, inclusive of `asOf`.
 *   - `storeId` optional — restricts to sales in one store.
 *
 * Output: a `Collection` of rows, one per customer with at least one
 * open sale, shaped:
 *
 *   [
 *     'customer_id'    => int,
 *     'customer_name'  => string,
 *     'customer_code'  => ?string,
 *     'total'          => string (4dp),   // sum of balance_due
 *     'b0_30'          => string (4dp),
 *     'b31_60'         => string (4dp),
 *     'b61_90'         => string (4dp),
 *     'b90_plus'       => string (4dp),
 *     'sales_count'    => int,
 *     'oldest_days'    => int,             // age of oldest open sale
 *   ]
 *
 * Sorted by total DESC so the biggest debtors surface at the top.
 *
 * Walk-in (customer_id IS NULL) sales with outstanding balance are
 * impossible in v1 (CompleteSale rejects under-paid walk-ins), so they
 * don't need bucketing here.
 */
class AgedReceivablesQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function __invoke(?CarbonImmutable $asOf = null, ?int $storeId = null): Collection
    {
        $asOf = ($asOf ?? CarbonImmutable::today())->startOfDay();

        // DB::table (not Sale::query) to bypass the SoftDeletes global
        // scope — its `WHERE sales.deleted_at IS NULL` clashes with the
        // aliased `s` we use to join customers. We still respect soft
        // deletes via explicit `whereNull('s.deleted_at')` below.
        $rows = DB::table('sales as s')
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->whereNull('s.deleted_at')
            ->whereNull('c.deleted_at')
            ->whereRaw('s.balance_due > 0')
            ->where('s.status', '!=', Sale::STATUS_VOIDED)
            ->whereDate('s.sale_date', '<=', $asOf->toDateString())
            ->when($storeId, fn ($q) => $q->where('s.store_id', $storeId))
            ->orderBy('c.id')
            ->orderBy('s.sale_date')
            ->get([
                's.id',
                's.customer_id',
                's.sale_date',
                's.balance_due',
                'c.name as customer_name',
                'c.code as customer_code',
            ]);

        $grouped = $rows->groupBy('customer_id');

        return $grouped->map(function ($customerSales, $customerId) use ($asOf) {
            $b0_30 = '0'; $b31_60 = '0'; $b61_90 = '0'; $b90_plus = '0';
            $total = '0';
            $oldestDays = 0;

            foreach ($customerSales as $sale) {
                $bal      = (string) $sale->balance_due;
                $saleDate = CarbonImmutable::parse($sale->sale_date)->startOfDay();
                // Carbon 3's diffInDays is signed; we want past sales
                // to read positive ("how many days old"). Future-dated
                // sales (clock skew) clamp to 0 so they bucket 0-30.
                $age = (int) max(0, $saleDate->diffInDays($asOf));

                $oldestDays = max($oldestDays, $age);
                $total = bcadd($total, $bal, 4);

                if ($age <= 30) {
                    $b0_30 = bcadd($b0_30, $bal, 4);
                } elseif ($age <= 60) {
                    $b31_60 = bcadd($b31_60, $bal, 4);
                } elseif ($age <= 90) {
                    $b61_90 = bcadd($b61_90, $bal, 4);
                } else {
                    $b90_plus = bcadd($b90_plus, $bal, 4);
                }
            }

            $first = $customerSales->first();
            return [
                'customer_id'   => (int) $customerId,
                'customer_name' => (string) $first->customer_name,
                'customer_code' => $first->customer_code,
                'total'         => $total,
                'b0_30'         => $b0_30,
                'b31_60'        => $b31_60,
                'b61_90'        => $b61_90,
                'b90_plus'      => $b90_plus,
                'sales_count'   => $customerSales->count(),
                'oldest_days'   => (int) $oldestDays,
            ];
        })->values()->sortByDesc(fn ($r) => (float) $r['total'])->values();
    }

    /**
     * Summary totals across every customer row — the four bucket sums
     * and the grand total. Used to render the KPI cards at the top of
     * the report.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, string>
     */
    public function summarise(Collection $rows): array
    {
        $sum = ['total' => '0', 'b0_30' => '0', 'b31_60' => '0', 'b61_90' => '0', 'b90_plus' => '0'];
        foreach ($rows as $r) {
            foreach ($sum as $k => $_) {
                $sum[$k] = bcadd($sum[$k], (string) $r[$k], 4);
            }
        }
        return $sum;
    }
}
