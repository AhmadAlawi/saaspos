<?php

namespace App\Actions\Expenses;

use App\Models\Expense;

/**
 * Soft-delete an expense. A linked cash-drawer pay-out keeps its
 * `expense_id` (nullOnDelete on the FK) so the drawer history survives.
 */
class DeleteExpense
{
    public function __invoke(Expense $expense): void
    {
        do_action('expense.before_delete', $expense);
        $expense->delete();
        do_action('expense.after_delete', $expense);
    }
}
