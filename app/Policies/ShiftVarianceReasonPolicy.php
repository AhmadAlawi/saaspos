<?php

namespace App\Policies;

use App\Models\ShiftVarianceReason;
use App\Models\User;

/**
 * Variance reasons are admin-managed lookup data — the close-shift
 * dropdown surfaces them. Gated by the same `shifts.close_others`
 * permission since that's the user persona who curates the list.
 */
class ShiftVarianceReasonPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('shifts.close_others'); }
    public function view(User $user, ShiftVarianceReason $r): bool { return $user->hasPermission('shifts.close_others'); }
    public function create(User $user): bool { return $user->hasPermission('shifts.close_others'); }
    public function update(User $user, ShiftVarianceReason $r): bool { return $user->hasPermission('shifts.close_others'); }
    public function delete(User $user, ShiftVarianceReason $r): bool { return $user->hasPermission('shifts.close_others'); }
}
