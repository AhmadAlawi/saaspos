<?php

namespace App\Services\Reports\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared per-account debit/credit aggregation over posted journal lines,
 * used by the Profit & Loss and Balance Sheet builders. `$from` is optional —
 * omit it for a running "as of `$to`" balance (Balance Sheet), pass both for a
 * period window (P&L).
 */
trait AggregatesLedger
{
    /**
     * @return Collection<int, object{code:string, name:string, type:string, debit:string, credit:string}>
     */
    protected function accountSums(?CarbonImmutable $from, CarbonImmutable $to, ?int $storeId): Collection
    {
        return DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->where('je.is_posted', true)
            ->when($from, fn ($q) => $q->whereDate('je.entry_date', '>=', $from->toDateString()))
            ->whereDate('je.entry_date', '<=', $to->toDateString())
            ->when($storeId, fn ($q) => $q->where('je.store_id', $storeId))
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->orderBy('a.code')
            ->selectRaw('
                a.code, a.name, a.type,
                COALESCE(SUM(jl.debit),  0) AS debit,
                COALESCE(SUM(jl.credit), 0) AS credit
            ')
            ->get();
    }

    /** Natural (normal-side-positive) balance for a debit- or credit-normal account. */
    protected function natural(string $type, string $debit, string $credit): string
    {
        return in_array($type, ['asset', 'expense'], true)
            ? bcsub($debit, $credit, 4)
            : bcsub($credit, $debit, 4);
    }
}
