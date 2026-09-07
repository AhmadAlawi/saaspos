<?php

namespace App\Policies;

use App\Models\ReturnReason;
use App\Models\User;

/**
 * Authorize the Return Reasons admin CRUD. Re-uses `sales.refund` —
 * the user who's allowed to process a refund is the one who needs to
 * maintain the reason list. Avoids inventing yet another permission
 * for what's effectively the same workflow boundary.
 */
class ReturnReasonPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('sales.refund'); }
    public function view(User $user, ReturnReason $r): bool { return $user->hasPermission('sales.refund'); }
    public function create(User $user): bool { return $user->hasPermission('sales.refund'); }
    public function update(User $user, ReturnReason $r): bool { return $user->hasPermission('sales.refund'); }
    public function delete(User $user, ReturnReason $r): bool { return $user->hasPermission('sales.refund'); }
}
