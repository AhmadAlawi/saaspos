<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Changes the session's active store. Verifies the user actually has
 * access to the target store before switching, then fires the
 * `store.switched` hook so plugins (and, later, the permission cache)
 * can react.
 */
class StoreSwitchController extends Controller
{
    public function __invoke(Request $request, Store $store): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->canAccessStore($store->id), 403);

        $from = current_store_id();

        $request->session()->put('active_store_id', $store->id);

        // Roles can differ per store; once the permission system lands its
        // per-request cache must be cleared here. Hook lets plugins react now.
        do_action('store.switched', $from, $store->id, $user);

        return redirect()
            ->back()
            ->with('success', __('stores.flash.switched', ['name' => $store->name]));
    }
}
