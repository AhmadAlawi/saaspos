<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Trial Balance (docs/features/accounting.md §7.1): every account's net
 * position as of a date, split into a debit or credit column. Because every
 * posted journal entry is itself balanced, the debit and credit totals must be
 * equal — if they aren't, the ledger has drifted and the screen flags it.
 *
 * The net per account (Σ debit − Σ credit) decides the column: a positive net
 * is a debit balance, a negative net a credit balance. That falls out of the
 * data without needing per-account-type rules.
 */
class TrialBalanceQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function __invoke(?CarbonImmutable $asOf = null, ?int $storeId = null): Collection
    {
        $asOf = ($asOf ?? CarbonImmutable::today())->startOfDay();

        $rows = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->where('je.is_posted', true)
            ->whereDate('je.entry_date', '<=', $asOf->toDateString())
            ->when($storeId, fn ($q) => $q->where('je.store_id', $storeId))
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->selectRaw('
                a.id, a.code, a.name, a.type,
                COALESCE(SUM(jl.debit),  0) AS total_debit,
                COALESCE(SUM(jl.credit), 0) AS total_credit
            ')
            ->orderBy('a.code')
            ->get();

        return $rows
            ->map(function ($r) {
                $net    = bcsub((string) $r->total_debit, (string) $r->total_credit, 4);
                $debit  = bccomp($net, '0', 4) > 0 ? $net : '0';
                $credit = bccomp($net, '0', 4) < 0 ? bcmul($net, '-1', 4) : '0';

                return [
                    'account_id' => (int) $r->id,
                    'code'       => (string) $r->code,
                    'name'       => (string) $r->name,
                    'type'       => (string) $r->type,
                    'debit'      => $debit,
                    'credit'     => $credit,
                ];
            })
            // Drop accounts that net to zero — the trial balance only lists
            // accounts carrying a balance.
            ->filter(fn ($row) => bccomp($row['debit'], '0', 4) !== 0 || bccomp($row['credit'], '0', 4) !== 0)
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{total_debit: string, total_credit: string, balanced: bool}
     */
    public function summarise(Collection $rows): array
    {
        $debit  = '0';
        $credit = '0';
        foreach ($rows as $r) {
            $debit  = bcadd($debit, (string) $r['debit'], 4);
            $credit = bcadd($credit, (string) $r['credit'], 4);
        }

        return [
            'total_debit'  => $debit,
            'total_credit' => $credit,
            'balanced'     => bccomp($debit, $credit, 4) === 0,
        ];
    }
}
