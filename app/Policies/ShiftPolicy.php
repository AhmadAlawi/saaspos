<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

/**
 * Shift access — opening + closing your own shift only needs
 * `shifts.open` / `shifts.close_own`; closing someone else's needs
 * `shifts.close_others`. The index list (every cashier's shifts, with
 * X/Z-report print buttons) is manager-only, gated on `shifts.view_all` —
 * a cashier still reaches their own shift directly via `show()`/`close()`
 * without ever seeing the list.
 *
 * Laravel auto-discovers App\Models\Shift → App\Policies\ShiftPolicy so
 * no AuthServiceProvider wiring is required.
 */
class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shifts.view_all');
    }

    public function view(User $user, Shift $shift): bool
    {
        if ($user->hasPermission('shifts.view_all')) return true;
        if ((int) $shift->user_id === (int) $user->id) return true;
        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('shifts.open');
    }

    public function close(User $user, Shift $shift): bool
    {
        if (! $shift->isOpen()) return false;
        if ((int) $shift->user_id === (int) $user->id) {
            return $user->hasPermission('shifts.close_own');
        }
        return $user->hasPermission('shifts.close_others');
    }
}
