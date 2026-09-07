<?php

namespace App\Actions\Accounting;

use App\Models\Account;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Services\Accounting\AccountMappingResolver;
use App\Services\Accounting\PaymentAccountResolver;

/**
 * Posts the journal entry for a recorded expense (docs/features/accounting.md
 * §8.3):
 *
 *   Dr  Expense account (from the category)   amount
 *   Dr  Tax Input                             tax_amount   (if any)
 *     Cr  Cash / Bank (payment method)          amount + tax
 *
 * The expense account comes from the category's mapped account, falling back
 * to Misc Expense. Idempotent; the `expense.after_create` listener wraps it so
 * a posting failure is logged, never blocking the expense.
 */
class PostExpenseEntry
{
    public function __construct(
        private PostJournalEntry $post,
        private AccountMappingResolver $mappings,
        private PaymentAccountResolver $paymentAccounts,
    ) {}

    public function __invoke(Expense $expense): ?JournalEntry
    {
        if ($this->alreadyPosted($expense)) {
            return null;
        }

        $expense->loadMissing(['category', 'paymentMethod']);
        $storeId = (int) $expense->store_id;
        $amount  = (string) $expense->amount;
        $tax     = (string) ($expense->tax_amount ?? '0');
        $total   = bcadd($amount, $tax, 4);

        if (bccomp($total, '0', 4) <= 0) {
            return null;
        }

        $paymentAccount = $this->paymentAccounts->resolve($expense->paymentMethod, $storeId);

        $lines = [];
        if (bccomp($amount, '0', 4) > 0) {
            $lines[] = ['account_id' => $this->expenseAccountId($expense), 'debit' => $amount, 'credit' => 0, 'description' => __('accounting.lines.expense')];
        }
        if (bccomp($tax, '0', 4) > 0) {
            $lines[] = ['account_id' => $this->mappings->id('tax_input', $storeId), 'debit' => $tax, 'credit' => 0, 'description' => __('accounting.lines.tax_input')];
        }
        $lines[] = ['account_id' => $paymentAccount->id, 'debit' => 0, 'credit' => $total, 'description' => __('accounting.lines.payment')];

        if (count($lines) < 2) {
            return null;
        }

        return ($this->post)([
            'store_id'       => $storeId,
            'entry_date'     => $expense->expense_date,
            'source'         => JournalEntry::SOURCE_EXPENSE,
            'reference_type' => Expense::class,
            'reference_id'   => $expense->id,
            'description'    => __('accounting.descriptions.expense', ['number' => $expense->number ?? ('#'.$expense->id)]),
            'created_by'     => $expense->created_by,
            'lines'          => $lines,
        ]);
    }

    private function expenseAccountId(Expense $expense): int
    {
        $accountId = $expense->category?->account_id;
        if ($accountId && Account::whereKey($accountId)->exists()) {
            return (int) $accountId;
        }

        return (int) Account::where('code', '7990')->value('id'); // Misc Expense fallback
    }

    private function alreadyPosted(Expense $expense): bool
    {
        return JournalEntry::where('source', JournalEntry::SOURCE_EXPENSE)
            ->where('reference_type', Expense::class)
            ->where('reference_id', $expense->id)
            ->exists();
    }
}
