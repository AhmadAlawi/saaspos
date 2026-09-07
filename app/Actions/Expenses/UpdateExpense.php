<?php

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Models\User;

/**
 * Update an editable expense. Audit + immutable fields (`number`,
 * `store_id`, `status`) are never touched here.
 *
 * @param array<string, mixed> $data
 */
class UpdateExpense
{
    public function __invoke(Expense $expense, array $data, ?User $user): Expense
    {
        do_action('expense.before_update', $expense, $data);

        $expense->fill($data);
        $expense->forceFill(['updated_by' => $user?->id]);
        $expense->save();

        $fresh = $expense->fresh();
        do_action('expense.after_update', $fresh);

        return $fresh;
    }
}
