<?php

namespace App\Services\Reports;

use App\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * General Ledger (docs/features/accounting.md §7.5): every posted journal line
 * for one account over a date range, with an opening balance and a running
 * balance per row.
 *
 * The balance runs in the account's natural direction — debit-normal accounts
 * (assets, expenses) accumulate debit − credit; credit-normal accounts
 * (liabilities, equity, income) accumulate credit − debit — so a normal
 * balance always reads positive.
 */
class GeneralLedgerQuery
{
    /**
     * @return array{account: ?Account, opening: string, closing: string, rows: list<array<string, mixed>>}
     */
    public function __invoke(int $accountId, CarbonImmutable $from, CarbonImmutable $to, ?int $storeId = null): array
    {
        $account = Account::find($accountId);
        if (! $account) {
            return ['account' => null, 'opening' => '0', 'closing' => '0', 'rows' => []];
        }

        $debitNormal = in_array($account->type, ['asset', 'expense'], true);

        // Opening balance = natural position of everything posted before `from`.
        $before = $this->base($accountId, $storeId)
            ->whereDate('je.entry_date', '<', $from->toDateString())
            ->selectRaw('COALESCE(SUM(jl.debit), 0) d, COALESCE(SUM(jl.credit), 0) c')
            ->first();
        $opening = $this->natural($debitNormal, (string) $before->d, (string) $before->c);

        $lines = $this->base($accountId, $storeId)
            ->whereDate('je.entry_date', '>=', $from->toDateString())
            ->whereDate('je.entry_date', '<=', $to->toDateString())
            ->orderBy('je.entry_date')
            ->orderBy('je.number')
            ->orderBy('jl.id')
            ->get([
                'je.entry_date', 'je.number', 'je.source',
                'je.description as je_description', 'je.reference_type', 'je.reference_id',
                'jl.debit', 'jl.credit', 'jl.description as line_description',
            ]);

        $running = $opening;
        $rows    = [];
        foreach ($lines as $l) {
            $running = bcadd($running, $this->natural($debitNormal, (string) $l->debit, (string) $l->credit), 4);
            $rows[]  = [
                'date'        => CarbonImmutable::parse($l->entry_date)->toDateString(),
                'number'      => (string) $l->number,
                'source'      => (string) $l->source,
                'description' => (string) ($l->line_description ?: $l->je_description ?: ''),
                'debit'       => (string) $l->debit,
                'credit'      => (string) $l->credit,
                'balance'     => $running,
            ];
        }

        return ['account' => $account, 'opening' => $opening, 'closing' => $running, 'rows' => $rows];
    }

    private function base(int $accountId, ?int $storeId)
    {
        return DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->where('jl.account_id', $accountId)
            ->where('je.is_posted', true)
            ->when($storeId, fn ($q) => $q->where('je.store_id', $storeId));
    }

    private function natural(bool $debitNormal, string $debit, string $credit): string
    {
        return $debitNormal ? bcsub($debit, $credit, 4) : bcsub($credit, $debit, 4);
    }
}
