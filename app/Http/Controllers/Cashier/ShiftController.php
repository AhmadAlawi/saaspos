<?php

namespace App\Http\Controllers\Cashier;

use App\Actions\Shifts\OpenShift;
use App\Actions\Shifts\OpenTradingDay;
use App\Exceptions\ShiftAlreadyOpen;
use App\Exceptions\TerminalShiftOpen;
use App\Exceptions\TradingDayNotOpen;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OpenShiftRequest;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cashier-surface shift opening (Slice A — shift enforcement).
 *
 * Lets the operator open a shift WITHOUT leaving /cashier when the store
 * enforces shifts. The shift gate (resources/js/cashier/shift-gate.js)
 * POSTs here, then reloads so the cashier boots with the shift bound and
 * {@see \App\Actions\Sales\CompleteSale} auto-binds `sales.shift_id`.
 *
 * Reuses the same OpenShift action + OpenShiftRequest as the admin
 * surface; only the redirect target differs (back to /cashier).
 */
class ShiftController extends Controller
{
    use RespondsJsonOrRedirect;

    public function open(OpenShiftRequest $request, OpenShift $open): JsonResponse|RedirectResponse
    {
        $user    = $request->user();
        $storeId = current_store_id() ?: default_store_id();
        $store   = Store::query()->findOrFail($storeId);

        // Bind the shift to the cookie-selected terminal (Slice B), if any.
        $data = $request->validated() + ['terminal_id' => current_terminal_id()];

        try {
            $shift = $open($data, $user, $store);
        } catch (ShiftAlreadyOpen $e) {
            // Already-open is a no-op success for the gate — the cashier
            // just needs *a* shift; surface it and let the screen reload.
            return $this->jsonOrRedirect(
                $request,
                __('shifts.flash.opened', ['number' => '#'.$e->existing->id]),
                route('cashier.index'),
            );
        } catch (TerminalShiftOpen $e) {
            // Another cashier holds this till's drawer — block with a
            // clear message instead of opening a parallel shift.
            return $this->jsonOrError($request, $e->getMessage());
        } catch (TradingDayNotOpen $e) {
            // Store requires an explicitly-opened day and none exists yet
            // — the gate's own "day" step is what should normally catch
            // this before the request even fires; this is the backstop.
            return $this->jsonOrError($request, $e->getMessage());
        }

        return $this->jsonOrRedirect(
            $request,
            __('shifts.flash.opened', ['number' => '#'.$shift->id]),
            route('cashier.index'),
        );
    }

    /**
     * "Open Day" reachable from the cashier's own shift gate — lets a
     * manager unblock every terminal at this store for today without
     * leaving /cashier. Gated on `shifts.open_day`, same trust tier as
     * `shifts.close_others` (see the counterpart in Admin\ShiftController).
     */
    public function openDay(Request $request, OpenTradingDay $openDay): JsonResponse|RedirectResponse
    {
        $user    = $request->user();
        $storeId = current_store_id() ?: default_store_id();
        $store   = Store::query()->findOrFail($storeId);

        abort_unless($user?->hasPermission('shifts.open_day', (int) $store->id), 403);

        $openDay($store, $user);

        return $this->jsonOrRedirect(
            $request,
            __('shifts.day_flash.opened'),
            route('cashier.index'),
        );
    }
}
