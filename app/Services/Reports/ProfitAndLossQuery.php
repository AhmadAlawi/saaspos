<?php

namespace App\Services\Reports;

use App\Services\Reports\Concerns\AggregatesLedger;
use Carbon\CarbonImmutable;

/**
 * Profit & Loss / Income Statement (docs/features/accounting.md §7.2) for a
 * period. Income accounts net credit-side; COGS + expenses net debit-side.
 * Sections: Income → COGS → Gross Profit → Operating (6xxx) → Other (7xxx)
 * expenses → Net Profit. Contra-revenue (Sales Returns / Discounts) naturally
 * nets negative inside Income, so Income totals to net sales.
 */
class ProfitAndLossQuery
{
    use AggregatesLedger;

    /** @return array<string, mixed> */
    public function __invoke(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId = null): array
    {
        $income = $cogs = $operating = $other = [];

        foreach ($this->accountSums($from, $to, $storeId) as $r) {
            $amount = $this->natural($r->type, (string) $r->debit, (string) $r->credit);
            if (bccomp($amount, '0', 4) === 0) {
                continue; // no activity in the period
            }

            $row = ['code' => (string) $r->code, 'name' => (string) $r->name, 'amount' => $amount];

            if ($r->type === 'income') {
                $income[] = $row;
            } elseif ($r->type === 'expense') {
                match (substr((string) $r->code, 0, 1)) {
                    '5'     => $cogs[]      = $row,
                    '6'     => $operating[] = $row,
                    default => $other[]     = $row,
                };
            }
        }

        $incomeTotal    = $this->sum($income);
        $cogsTotal      = $this->sum($cogs);
        $grossProfit    = bcsub($incomeTotal, $cogsTotal, 4);
        $operatingTotal = $this->sum($operating);
        $otherTotal     = $this->sum($other);
        $totalExpenses  = bcadd($operatingTotal, $otherTotal, 4);
        $netProfit      = bcsub($grossProfit, $totalExpenses, 4);

        return [
            'income'         => ['rows' => $income, 'total' => $incomeTotal],
            'cogs'           => ['rows' => $cogs, 'total' => $cogsTotal],
            'gross_profit'   => $grossProfit,
            'operating'      => ['rows' => $operating, 'total' => $operatingTotal],
            'other'          => ['rows' => $other, 'total' => $otherTotal],
            'total_expenses' => $totalExpenses,
            'net_profit'     => $netProfit,
            'gross_margin'   => $this->margin($grossProfit, $incomeTotal),
            'net_margin'     => $this->margin($netProfit, $incomeTotal),
        ];
    }

    /** @param list<array{amount: string}> $rows */
    private function sum(array $rows): string
    {
        $total = '0';
        foreach ($rows as $r) {
            $total = bcadd($total, $r['amount'], 4);
        }

        return $total;
    }

    /** Percentage of income, 2dp; null-safe on zero income. */
    private function margin(string $value, string $income): ?string
    {
        if (bccomp($income, '0', 4) <= 0) {
            return null;
        }

        return bcmul(bcdiv($value, $income, 6), '100', 2);
    }
}
