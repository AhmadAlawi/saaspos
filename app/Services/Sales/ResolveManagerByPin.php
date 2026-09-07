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
 */
class ResolveManagerByPin
{
    public function __invoke(string $pin, int $storeId): ?User
    {
        $candidates = User::query()
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->whereHas('stores', fn ($q) => $q->where('stores.id', $storeId))
            ->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($pin, (string) $candidate->pin)) {
                return $candidate;
            }
        }

        return null;
    }
}
