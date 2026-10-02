<?php

namespace App\Services\Sales;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Resolves which user just typed a 6-digit PIN on the cashier's manager-
 * approval numpad (discount, refund). There's no username/email step —
 * per the PIN-pad design, the cashier only ever types digits — so this
 * iterates every active user assigned a role at the store who has a PIN
 * set, and hash-checks the entered PIN against each. Small candidate set
 * (managers/admins only, per store) makes the O(n) scan cheap; the
 * caller is responsible for rate-limiting attempts.
 *
 * Super admins are ALWAYS a candidate, regardless of store — a super
 * admin has no `store_user` row for most stores (they don't run a till
 * there day to day), but Gate::before already grants them every
 * permission everywhere. Without this, their PIN silently failed to
 * resolve at any store they weren't explicitly staffed at, surfacing as
 * a confusing "you don't have permission" even though they're the one
 * account that should never be blocked.
 */
class ResolveManagerByPin
{
    public function __invoke(string $pin, int $storeId): ?User
    {
        $candidates = User::query()
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->where(function ($q) use ($storeId) {
                $q->where('is_super_admin', true)
                    ->orWhereHas('stores', fn ($q2) => $q2->where('stores.id', $storeId));
            })
            ->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($pin, (string) $candidate->pin)) {
                return $candidate;
            }
        }

        return null;
    }
}
