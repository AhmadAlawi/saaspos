<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

/**
 * Expense-category management is gated by the expenses permissions:
 * viewing needs `expenses.view`; add/edit/delete need `expenses.update`.
 * Super admins bypass via Gate::before.
 */
class ExpenseCategoryPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('expenses.view'); }
    public function view(User $user, ExpenseCategory $c): bool   { return $user->hasPermission('expenses.view'); }
    public function create(User $user): bool  { return $user->hasPermission('expenses.update'); }
    public function update(User $user, ExpenseCategory $c): bool { return $user->hasPermission('expenses.update'); }
    public function delete(User $user, ExpenseCategory $c): bool { return $user->hasPermission('expenses.update'); }
}
