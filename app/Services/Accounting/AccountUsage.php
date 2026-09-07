<?php

namespace App\Services\Accounting;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

/**
 * Answers "is this account safe to change?" for the chart-of-accounts editor.
 * An account referenced by a posted journal line has an immutable code and
 * can't be deleted; an account backing a business mapping can't be deleted or
 * deactivated. Exposes both per-account checks (used by the write actions to
 * enforce) and bulk lookups (used by the controller to flag the whole tree in
 * one query each).
 */
class AccountUsage
{
    /** Posted journal-line count for one account. >0 ⇒ code locked + undeletable. */
    public function postedLineCount(Account $account): int
    {
        return DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->where('jl.account_id', $account->id)
            ->where('je.is_posted', true)
            ->count();
    }

    /** Net balance Σ(debit − credit) over posted lines, as a 4dp string. */
    public function balance(Account $account): string
    {
        $bal = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->where('jl.account_id', $account->id)
            ->where('je.is_posted', true)
            ->selectRaw('COALESCE(SUM(jl.debit) - SUM(jl.credit), 0) as bal')
            ->value('bal');

        return (string) ($bal ?? '0');
    }

    /** How many business-event mappings point at this account (any store). */
    public function mappingCount(Account $account): int
    {
        return DB::table('account_mappings')->where('account_id', $account->id)->count();
    }

    // ── Bulk lookups (one query each) for the tree view ────────────────

    /** @return array<int,string> account_id => natural-signed net balance */
    public function balances(): array
    {
        return DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->where('je.is_posted', true)
            ->groupBy('jl.account_id', 'a.type')
            ->selectRaw("jl.account_id, a.type, COALESCE(SUM(jl.debit) - SUM(jl.credit), 0) as raw")
            ->get()
            ->mapWithKeys(fn ($r) => [
                (int) $r->account_id => in_array($r->type, ['asset', 'expense'], true)
                    ? (string) $r->raw
                    : bcmul((string) $r->raw, '-1', 4),
            ])
            ->all();
    }

    /** @return array<int,true> set of account_ids referenced by ≥1 posted line */
    public function postedAccountIds(): array
    {
        return DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->where('je.is_posted', true)
            ->distinct()
            ->pluck('jl.account_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /** @return array<int,true> set of account_ids referenced by ≥1 mapping */
    public function mappedAccountIds(): array
    {
        return DB::table('account_mappings')
            ->distinct()
            ->pluck('account_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }
}
