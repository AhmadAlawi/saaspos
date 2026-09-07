<?php

namespace App\Services\Reports;

use App\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cash Flow — direct method (docs/features/accounting.md §7.4). Follows the
 * money into and out of the cash / bank accounts over a period and groups each
 * movement into an activity:
 *
 *   - Operating: auto-posted trading (sales, refunds, customer & supplier
 *     payments, expenses, cash variance) — grouped by the entry's source.
 *   - Investing / Financing: manual entries, classified by their counter
 *     account (fixed assets → investing; equity or long-term loans → financing).
 *
 * Net change + the opening cash balance gives the closing balance.
 */
class CashFlowQuery
{
    /** Cash + bank accounts (cash equivalents). Cheques-in-hand aren't cash yet. */
    private const CASH_CODES = ['1010', '1020', '1021'];

    /** Auto-posted sources that are always operating activity → row label. */
    private const OPERATING_SOURCES = [
        'sale'             => 'reports.cash_flow.from_sales',
        'customer_payment' => 'reports.cash_flow.from_customers',
        'sale_return'      => 'reports.cash_flow.refunds',
        'supplier_payment' => 'reports.cash_flow.to_suppliers',
        'expense'          => 'reports.cash_flow.expenses_paid',
        'shift_variance'   => 'reports.cash_flow.cash_variance',
    ];

    /** @return array<string, mixed> */
    public function __invoke(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId = null): array
    {
        $cashIds = Account::whereIn('code', self::CASH_CODES)->pluck('id')->all();
        if ($cashIds === []) {
            return $this->emptyResult();
        }

        $opening = $this->openingBalance($cashIds, $from, $storeId);

        // Every cash-account line in the period, tagged with its entry's source.
        $lines = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->whereIn('jl.account_id', $cashIds)
            ->where('je.is_posted', true)
            ->whereDate('je.entry_date', '>=', $from->toDateString())
            ->whereDate('je.entry_date', '<=', $to->toDateString())
            ->when($storeId, fn ($q) => $q->where('je.store_id', $storeId))
            ->get(['jl.journal_entry_id as je_id', 'je.source', 'jl.debit', 'jl.credit']);

        $sourceNet  = []; // operating sources → net cash
        $otherJeNet = []; // manual/other je_id → net cash
        foreach ($lines as $l) {
            $net = bcsub((string) $l->debit, (string) $l->credit, 4);
            if (array_key_exists($l->source, self::OPERATING_SOURCES)) {
                $sourceNet[$l->source] = bcadd($sourceNet[$l->source] ?? '0', $net, 4);
            } else {
                $otherJeNet[$l->je_id] = bcadd($otherJeNet[$l->je_id] ?? '0', $net, 4);
            }
        }

        $operating = [];
        foreach (self::OPERATING_SOURCES as $src => $labelKey) {
            if (isset($sourceNet[$src]) && bccomp($sourceNet[$src], '0', 4) !== 0) {
                $operating[] = ['label' => __($labelKey), 'amount' => $sourceNet[$src]];
            }
        }

        [$otherOperating, $investing, $financing] = $this->classifyOther($otherJeNet, $cashIds);
        $operating = array_merge($operating, $otherOperating);

        $opTotal  = $this->sum($operating);
        $invTotal = $this->sum($investing);
        $finTotal = $this->sum($financing);
        $netChange = bcadd(bcadd($opTotal, $invTotal, 4), $finTotal, 4);

        return [
            'operating'  => ['rows' => $operating, 'total' => $opTotal],
            'investing'  => ['rows' => $investing, 'total' => $invTotal],
            'financing'  => ['rows' => $financing, 'total' => $finTotal],
            'net_change' => $netChange,
            'opening'    => $opening,
            'closing'    => bcadd($opening, $netChange, 4),
        ];
    }

    /**
     * Classify manual / non-operational cash movements by their dominant
     * counter account.
     *
     * @param  array<int, string>  $otherJeNet  je_id → net cash
     * @return array{0: list<array>, 1: list<array>, 2: list<array>}  [operating, investing, financing]
     */
    private function classifyOther(array $otherJeNet, array $cashIds): array
    {
        if ($otherJeNet === []) {
            return [[], [], []];
        }

        $counterLines = DB::table('journal_lines as jl')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->whereIn('jl.journal_entry_id', array_keys($otherJeNet))
            ->whereNotIn('jl.account_id', $cashIds)
            ->get(['jl.journal_entry_id as je_id', 'a.code', 'a.name', 'a.type', 'jl.debit', 'jl.credit']);

        // Dominant counter account per entry (largest absolute line).
        $counter = [];
        $absMax  = [];
        foreach ($counterLines as $l) {
            $abs = abs((float) $l->debit - (float) $l->credit);
            if (! isset($absMax[$l->je_id]) || $abs > $absMax[$l->je_id]) {
                $absMax[$l->je_id] = $abs;
                $counter[$l->je_id] = $l;
            }
        }

        $groups = [];
        foreach ($otherJeNet as $jeId => $net) {
            $c        = $counter[$jeId] ?? null;
            $activity = $this->activityFor($c);
            $label    = $c->name ?? __('reports.cash_flow.other');
            $key      = $activity.'|'.$label;

            $groups[$key]['label']    = $label;
            $groups[$key]['activity'] = $activity;
            $groups[$key]['amount']   = bcadd($groups[$key]['amount'] ?? '0', $net, 4);
        }

        $operating = $investing = $financing = [];
        foreach ($groups as $g) {
            if (bccomp($g['amount'], '0', 4) === 0) {
                continue;
            }
            $row = ['label' => $g['label'], 'amount' => $g['amount']];
            match ($g['activity']) {
                'investing' => $investing[] = $row,
                'financing' => $financing[] = $row,
                default     => $operating[] = $row,
            };
        }

        return [$operating, $investing, $financing];
    }

    private function activityFor(?object $counter): string
    {
        if (! $counter) {
            return 'operating';
        }

        $code = (string) $counter->code;
        if (str_starts_with($code, '15')) {
            return 'investing'; // fixed assets
        }
        if ($counter->type === 'equity' || str_starts_with($code, '25')) {
            return 'financing'; // owner equity / long-term loans
        }

        return 'operating';
    }

    private function openingBalance(array $cashIds, CarbonImmutable $from, ?int $storeId): string
    {
        $bal = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->whereIn('jl.account_id', $cashIds)
            ->where('je.is_posted', true)
            ->whereDate('je.entry_date', '<', $from->toDateString())
            ->when($storeId, fn ($q) => $q->where('je.store_id', $storeId))
            ->selectRaw('COALESCE(SUM(jl.debit) - SUM(jl.credit), 0) as bal')
            ->value('bal');

        return (string) ($bal ?? '0');
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

    /** @return array<string, mixed> */
    private function emptyResult(): array
    {
        return [
            'operating'  => ['rows' => [], 'total' => '0'],
            'investing'  => ['rows' => [], 'total' => '0'],
            'financing'  => ['rows' => [], 'total' => '0'],
            'net_change' => '0',
            'opening'    => '0',
            'closing'    => '0',
        ];
    }
}
