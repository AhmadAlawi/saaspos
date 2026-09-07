<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

/**
 * Expense authorization. Permissions seeded by PermissionsSeeder:
 * `expenses.view`, `.create`, `.update`, `.delete`, `.export`.
 * Super admins bypass via Gate::before.
 */
class ExpensePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('expenses.view'); }
    public function view(User $user, Expense $e): bool   { return $user->hasPermission('expenses.view'); }
    public function create(User $user): bool  { return $user->hasPermission('expenses.create'); }
    public function update(User $user, Expense $e): bool { return $user->hasPermission('expenses.update'); }
    public function delete(User $user, Expense $e): bool { return $user->hasPermission('expenses.delete'); }
    public function export(User $user): bool  { return $user->hasPermission('expenses.export'); }
}
