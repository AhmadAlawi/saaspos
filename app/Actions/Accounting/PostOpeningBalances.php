<?php

namespace App\Actions\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Posts the migration opening-balance entry (docs/features/accounting.md §9).
 * Each account's balance goes on its natural side; the special "Opening
 * Balance Equity" account (3090) is the plug that keeps the entry balanced —
 * it nets to zero once a complete, balanced trial balance has been entered.
 *
 * Opening balances stay editable until the first real transaction posts, so
 * this re-posts from scratch every time: any previous opening-balance entry is
 * wiped first. The guard for "is it still editable" lives in the controller.
 */
class PostOpeningBalances
{
    public function __construct(private PostJournalEntry $post) {}

    /**
     * @param  array{store_id:int, entry_date:string, balances:array<int|string,string>}  $data
     * @return JournalEntry|null  null when nothing was entered
     */
    public function __invoke(array $data): ?JournalEntry
    {
        return DB::transaction(function () use ($data) {
            // Editable until first real txn → always rebuild from scratch.
            foreach (JournalEntry::where('source', JournalEntry::SOURCE_OPENING_BALANCE)->get() as $je) {
                $je->lines()->delete();
                $je->delete();
            }

            $obeId    = (int) Account::where('code', '3090')->value('id');
            $accounts = Account::whereIn('id', array_map('intval', array_keys($data['balances'])))
                ->get()->keyBy('id');

            $lines = [];
            $debit = '0';
            $credit = '0';

            foreach ($data['balances'] as $accountId => $amount) {
                $amount = (string) $amount;
                if (bccomp($amount, '0', 4) <= 0) {
                    continue;
                }

                $account = $accounts->get((int) $accountId);
                if (! $account || $account->id === $obeId) {
                    continue; // unknown, or the plug itself — skip
                }

                if (in_array($account->type, ['asset', 'expense'], true)) {
                    $lines[] = ['account_id' => $account->id, 'debit' => $amount, 'credit' => 0, 'description' => __('accounting.opening.line')];
                    $debit   = bcadd($debit, $amount, 4);
                } else {
                    $lines[] = ['account_id' => $account->id, 'debit' => 0, 'credit' => $amount, 'description' => __('accounting.opening.line')];
                    $credit  = bcadd($credit, $amount, 4);
                }
            }

            if ($lines === []) {
                return null;
            }

            // Plug the difference into Opening Balance Equity so the entry balances.
            $diff = bcsub($debit, $credit, 4);
            $cmp  = bccomp($diff, '0', 4);
            if ($cmp > 0) {
                $lines[] = ['account_id' => $obeId, 'debit' => 0, 'credit' => $diff, 'description' => __('accounting.opening.equity')];
            } elseif ($cmp < 0) {
                $lines[] = ['account_id' => $obeId, 'debit' => bcmul($diff, '-1', 4), 'credit' => 0, 'description' => __('accounting.opening.equity')];
            }

            return ($this->post)([
                'store_id'    => (int) ($data['store_id'] ?: (current_store_id() ?: Store::query()->value('id'))),
                'entry_date'  => $data['entry_date'],
                'source'      => JournalEntry::SOURCE_OPENING_BALANCE,
                'description' => __('accounting.opening.description'),
                'lines'       => $lines,
            ]);
        });
    }
}
