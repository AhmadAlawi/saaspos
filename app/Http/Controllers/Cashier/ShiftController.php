<?php

namespace App\Http\Controllers\Cashier;

use App\Actions\Cashier\RecordCashierActivity;
use App\Actions\Hardware\PrepareCashDrawerSlipPayload;
use App\Actions\Shifts\OpenShift;
use App\Actions\Shifts\OpenTradingDay;
use App\Actions\Shifts\RecordCashDrawerEntry;
use App\Exceptions\ShiftAlreadyOpen;
use App\Exceptions\ShiftNotOpen;
use App\Exceptions\TerminalShiftOpen;
use App\Exceptions\TradingDayNotOpen;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OpenShiftRequest;
use App\Http\Requests\Cashier\PinDrawerOpenRequest;
use App\Models\CashDrawerEntry;
use App\Models\Shift;
use App\Models\Store;
use App\Services\Auth\ResolveUserByPin;
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

    public function open(OpenShiftRequest $request, OpenShift $open, RecordCashierActivity $record): JsonResponse|RedirectResponse
    {
        $user    = $request->user();
        $storeId = current_store_id() ?: default_store_id();
        $store   = Store::query()->findOrFail($storeId);

        // Bind the shift to the cookie-selected terminal (Slice B), if any.
        $data = $request->validated() + ['terminal_id' => current_terminal_id()];

        try {
            $shift = $open($data, $user, $store);
            try {
                $record(['type' => 'shift', 'action' => 'shift.open', 'reference_type' => 'Shift', 'reference_id' => $shift->id], $request);
            } catch (\Throwable) { /* best-effort */ }
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

    /**
     * Focus mode's "Open drawer" rail button. Unlike the admin/overflow-
     * menu version of this action (gated on `cash_drawer.open_no_sale`),
     * this one is open to every active team member — the PIN itself is
     * the gate, and it's WHO gets logged against the audit entry, not
     * necessarily the cashier session that's currently signed into this
     * terminal. Recorded against the shift currently open for the
     * SIGNED-IN session (there's only ever one terminal's drawer in
     * front of whoever is standing here), same resolution
     * {@see \App\Http\Controllers\Admin\SaleController::buildCashierPayload()}
     * uses for `activeShiftId`.
     *
     * When NO shift is open at all (e.g. a manager popping the drawer
     * before any cashier has clocked in today), the entry is recorded
     * shift-less (`shift_id` NULL) instead of refusing outright —
     * {@see \App\Actions\Shifts\RecordCashDrawerEntry} only allows that
     * for `drawer_open_no_sale`; it never touches any shift's Z-report.
     */
    public function openDrawerWithPin(
        PinDrawerOpenRequest $request,
        ResolveUserByPin $resolve,
        RecordCashDrawerEntry $record,
        PrepareCashDrawerSlipPayload $prepareSlip,
    ): JsonResponse|RedirectResponse {
        $request->ensureIsNotRateLimited();

        $performer = $resolve($request->string('pin')->value());
        if (! $performer) {
            $request->hitRateLimit();
            return $this->jsonOrError($request, __('cash_drawer.errors.pin_invalid'));
        }
        $request->clearRateLimit();

        $sessionUser = $request->user();
        $storeId     = current_store_id() ?: default_store_id();
        $shift       = $sessionUser ? Shift::openForCashier((int) $storeId, (int) $sessionUser->id) : null;

        try {
            $record($shift, [
                'type'   => CashDrawerEntry::TYPE_DRAWER_OPEN_NO_SALE,
                'reason' => __('cash_drawer.reasons.focus_pin_open', ['name' => $performer->name]),
            ], $performer);
        } catch (ShiftNotOpen $e) {
            return $this->jsonOrError($request, $e->getMessage());
        }

        // Resolved server-side against the SHIFT's own bound terminal —
        // same as the sale receipt — not the page's client-side printer
        // meta tag, which can be stale/empty if this workstation's
        // terminal binding predates or never got hardware settings.
        $printPayload = $prepareSlip($shift, $performer->name);

        return response()->json([
            'message'       => __('cash_drawer.flash.drawer_opened_no_sale'),
            'performed_by'  => $performer->name,
            'print_payload' => $printPayload,
        ]);
    }
}
