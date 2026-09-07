<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to authenticated admin routes. Guarantees that
 * `session('active_store_id')` points at a store the user can actually
 * reach, picking a sensible default when it doesn't.
 *
 * Resolution order (docs/features/multi-store.md §4.2):
 *   1. A valid, still-accessible store already in session → keep it.
 *   2. The user's `default_store_id`, if accessible.
 *   3. The user's single store, if they have exactly one.
 *   4. Otherwise the first store they can access.
 *
 * A full post-login store picker (for >1 stores with no default) is
 * deferred; until then we auto-pick the first store and let the user
 * change it from the top-bar switcher.
 */
class EnsureStoreSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $current = $request->session()->get('active_store_id');

            if (! $current || ! $user->canAccessStore((int) $current)) {
                $request->session()->put('active_store_id', $this->resolveStoreId($user));
            }
        }

        return $next($request);
    }

    private function resolveStoreId($user): ?int
    {
        if ($user->default_store_id && $user->canAccessStore((int) $user->default_store_id)) {
            return (int) $user->default_store_id;
        }

        // Fall back to the company default store, if the user can reach it.
        if (($companyDefault = default_store_id()) && $user->canAccessStore($companyDefault)) {
            return $companyDefault;
        }

        return $user->accessibleStores()->first()?->id;
    }
}
