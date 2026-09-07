<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Resolves which user just typed their 6-digit PIN on the login screen's
 * numpad. Same identification shape as {@see \App\Services\Sales\ResolveManagerByPin}
 * (no username step, O(n) hash-check over PIN-holding candidates — small
 * candidate set keeps it cheap, caller rate-limits attempts) but NOT
 * store-scoped: store/terminal selection happens only after login in this
 * app (see `current_store_id()`/`current_terminal()` in app/helpers.php),
 * so there's no store context yet to narrow the scan by.
 */
class ResolveUserByPin
{
    public function __invoke(string $pin): ?User
    {
        $candidates = User::query()
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($pin, (string) $candidate->pin)) {
                return $candidate;
            }
        }

        return null;
    }
}
