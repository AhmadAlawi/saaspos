<?php

namespace App\Services\Reports;

use App\Services\Reports\Concerns\AggregatesLedger;
use Carbon\CarbonImmutable;

/**
 * Balance Sheet (docs/features/accounting.md §7.3) as of a date. Assets net
 * debit-side; liabilities + equity net credit-side. Current-earnings (net
 * income to date) is added to equity — until a year-end close moves prior
 * profit into Retained Earnings, all profit still sits in the income/expense
 * accounts, so it's picked up here.
 *
 * Because every posted entry balances, Assets always equals
 * Liabilities + Equity + Net Income; the screen flags it red if it doesn't.
 */
class BalanceSheetQuery
{
    use AggregatesLedger;

    /** @return array<string, mixed> */
    public function __invoke(CarbonImmutable $asOf, ?int $storeId = null): array
    {
        $assets = $liabilities = $equity = [];
        $income = '0';
        $expenses = '0';

        foreach ($this->accountSums(null, $asOf, $storeId) as $r) {
            $natural = $this->natural($r->type, (string) $r->debit, (string) $r->credit);
            $row     = ['code' => (string) $r->code, 'name' => (string) $r->name, 'amount' => $natural];
            $nonZero = bccomp($natural, '0', 4) !== 0;

            switch ($r->type) {
                case 'asset':     $nonZero && $assets[]      = $row; break;
                case 'liability': $nonZero && $liabilities[] = $row; break;
                case 'equity':    $nonZero && $equity[]      = $row; break;
                case 'income':    $income   = bcadd($income, $natural, 4); break;
                case 'expense':   $expenses = bcadd($expenses, $natural, 4); break;
            }
        }

        $netIncome       = bcsub($income, $expenses, 4);
        $assetsTotal     = $this->sum($assets);
        $liabTotal       = $this->sum($liabilities);
        $equityTotal     = $this->sum($equity);
        $totalEquity     = bcadd($equityTotal, $netIncome, 4);
        $totalLiabEquity = bcadd($liabTotal, $totalEquity, 4);

        return [
            'assets'                   => ['rows' => $assets, 'total' => $assetsTotal],
            'liabilities'              => ['rows' => $liabilities, 'total' => $liabTotal],
            'equity'                   => ['rows' => $equity, 'total' => $equityTotal],
            'net_income'               => $netIncome,
            'total_equity'             => $totalEquity,
            'total_assets'             => $assetsTotal,
            'total_liabilities_equity' => $totalLiabEquity,
            'balanced'                 => bccomp($assetsTotal, $totalLiabEquity, 4) === 0,
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
}
