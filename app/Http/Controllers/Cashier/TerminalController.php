<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Terminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cashier-surface terminal selection (Slice B — terminal gate).
 *
 * Lets the operator bind this workstation to a terminal WITHOUT the
 * `terminals.view` admin permission — anyone who can ring up a sale can
 * pick which till they're on. Sets the same long-lived `pos_terminal_id`
 * cookie {@see current_terminal()} reads, so the choice survives reloads
 * and the shift + sale rows stamp with it.
 *
 * The terminal must belong to the active store; switching stores stays
 * an admin-side concern (Admin → Terminals), so this never changes the
 * active store the way {@see \App\Http\Controllers\Admin\TerminalController::select} does.
 */
class TerminalController extends Controller
{
    use RespondsJsonOrRedirect;

    public function select(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Sale::class);

        $storeId  = current_store_id() ?: default_store_id();
        $terminal = Terminal::query()
            ->whereKey((int) $request->input('terminal_id'))
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->first();

        if (! $terminal) {
            return $this->jsonOrError($request, __('terminals.select.inactive'));
        }

        $user = $request->user();
        abort_unless($user && $user->canAccessStore($terminal->store_id), 403);

        cookie()->queue(cookie()->forever('pos_terminal_id', (string) $terminal->id));

        return $this->jsonOrRedirect(
            $request,
            __('terminals.select.flash', ['name' => $terminal->name]),
            route('cashier.index'),
        );
    }
}
