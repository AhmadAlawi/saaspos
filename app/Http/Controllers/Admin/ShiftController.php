<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Hardware\PrepareZReportPayload;
use App\Actions\Shifts\CloseShift;
use App\Actions\Shifts\ComputeShiftTotals;
use App\Actions\Shifts\OpenShift;
use App\Actions\Shifts\RecordCashDrawerEntry;
use App\Exceptions\ShiftAlreadyOpen;
use App\Exceptions\ShiftNotOpen;
use App\Exceptions\VarianceReasonRequired;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CloseShiftRequest;
use App\Http\Requests\Admin\OpenShiftRequest;
use App\Http\Requests\Admin\RecordCashDrawerEntryRequest;
use App\Models\CashDrawerEntry;
use App\Models\Shift;
use App\Models\ShiftVarianceReason;
use App\Models\Store;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin surface for shifts — index / open / close / show.
 *
 * Slice 1 scopes shift uniqueness to (store, user). The session-active
 * store (set by the topbar switcher) is the store every action runs
 * against.
 */
class ShiftController extends Controller
{
    use RendersDataTableRows;
    use RespondsJsonOrRedirect;

    public function __construct(
        private readonly ComputeShiftTotals $compute,
    ) {}

    /* ── Index ──────────────────────────────────────────────────── */

    /**
     * Shifts list — server-paginated. Only the first page renders inline;
     * search, sort and paging round-trip to {@see rows()}. Storewise +
     * permission-scoped (a cashier without `shifts.view_all` sees only theirs).
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Shift::class);

        $user    = $request->user();
        $storeId = current_store_id() ?: default_store_id();
        $perPage = $this->dtPerPage($request);

        $paginator = $this->listQuery(new Request(['per_page' => $perPage]))->paginate($perPage);
        $summary   = $this->summaryFor(new Request());

        $activeShift = $user
            ? Shift::openForCashier($storeId, (int) $user->id)
            : null;

        // Every open trading day for this store, one per terminal —
        // surfaced here (not just on whichever terminal's own cashier
        // screen) because with several terminals there can be several
        // of these open at once, and a manager working from the back
        // office has no other way to see or close any of them.
        $canCloseDay = $user?->hasPermission('shifts.close_others', (int) $storeId) ?? false;
        $openTradingDays = $canCloseDay
            ? \App\Models\TradingDay::query()
                ->where('store_id', $storeId)
                ->where('status', \App\Models\TradingDay::STATUS_OPEN)
                ->with(['terminal:id,name,code', 'openedBy:id,name'])
                ->withCount('shifts')
                ->orderBy('terminal_id')
                ->get()
            : collect();

        return view('admin.shifts.index', [
            'shifts'          => $paginator->getCollection(),
            'perPage'         => $perPage,
            'total'           => $paginator->total(),
            'totalPages'      => max(1, $paginator->lastPage()),
            'summaryCards'    => $this->summaryCards($summary),
            'activeShift'     => $activeShift,
            'openTradingDays' => $openTradingDays,
            'canCloseDay'     => $canCloseDay,
        ]);
    }

    /**
     * One page of shift rows as an HTML fragment plus summary meta. See
     * {@see RendersDataTableRows}.
     */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shift::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.shifts._rows', 'shifts', [
            'summary' => $this->summaryFor($request),
        ]);
    }

    /**
     * Store- and permission-scoped list, plus optional search on shift id /
     * cashier name. No eager loads or ordering — shared with the summary
     * aggregate so the cards describe the exact set on screen.
     *
     * @return Builder<Shift>
     */
    private function filteredBase(Request $request): Builder
    {
        $user       = $request->user();
        $storeId    = current_store_id() ?: default_store_id();
        $canViewAll = $user?->hasPermission('shifts.view_all') ?? false;

        $query = Shift::query()
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when(! $canViewAll && $user, fn ($q) => $q->where('user_id', $user->id));

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(fn (Builder $w) => $w
                ->where('id', 'like', "%{$q}%")
                ->orWhereHas('cashier', fn ($c) => $c->where('name', 'like', "%{$q}%")));
        }

        return $query;
    }

    /** @return Builder<Shift> */
    private function listQuery(Request $request): Builder
    {
        return $this->filteredBase($request)
            ->with(['store:id,name', 'cashier:id,name'])
            ->orderByDesc('opened_at')
            ->orderByDesc('id');
    }

    /**
     * Summary-card totals honoring the scope + search. One aggregate query,
     * values arriving display-ready.
     *
     * @return array{total:string, open:string, variance:string}
     */
    private function summaryFor(Request $request): array
    {
        $row = $this->filteredBase($request)
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END), 0) AS open_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'closed_with_variance' THEN 1 ELSE 0 END), 0) AS variance_count")
            ->first();

        return [
            'total'    => number_format((int) ($row->total_count ?? 0)),
            'open'     => number_format((int) ($row->open_count ?? 0)),
            'variance' => number_format((int) ($row->variance_count ?? 0)),
        ];
    }

    /**
     * @param  array{total:string, open:string, variance:string}  $summary
     * @return array<int, array<string, mixed>>
     */
    private function summaryCards(array $summary): array
    {
        return [
            ['label' => __('shifts.summary.total'),    'value' => $summary['total'],    'key' => 'total'],
            ['label' => __('shifts.summary.open'),     'value' => $summary['open'],     'key' => 'open',     'tone' => 'accent'],
            ['label' => __('shifts.summary.variance'), 'value' => $summary['variance'], 'key' => 'variance', 'tone' => 'warning'],
        ];
    }

    /* ── Open ───────────────────────────────────────────────────── */

    public function openForm(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Shift::class);

        $user    = $request->user();
        $storeId = current_store_id() ?: default_store_id();
        if (! $storeId) {
            return redirect()
                ->route('admin.shifts.index')
                ->with('error', __('shifts.errors.no_store_selected'));
        }

        $existing = Shift::openForCashier($storeId, (int) $user->id);
        if ($existing) {
            return redirect()->route('admin.shifts.show', $existing);
        }

        $store = Store::query()->findOrFail($storeId);

        // Store-scoped terminal picker. A terminal is required to open a shift
        // when the store has any; the picker defaults to the currently-bound
        // terminal (pos_terminal_id cookie).
        $terminals = \App\Models\Terminal::query()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.shifts.open', [
            'store'             => $store,
            'terminals'         => $terminals,
            'selectedTerminal'  => current_terminal_id(),
            'denominations'     => \App\Support\Denominations::forActiveCurrency(),
        ]);
    }

    public function store(OpenShiftRequest $request, OpenShift $open): JsonResponse|RedirectResponse
    {
        $user    = $request->user();
        $storeId = current_store_id() ?: default_store_id();
        $store   = Store::query()->findOrFail($storeId);

        // Bind the shift to the terminal chosen on the open form (validated to
        // belong to this store + be active). Persist it as the browser's active
        // terminal so the cashier context matches this shift.
        $data       = $request->validated();
        $terminalId = $request->integer('terminal_id') ?: null;
        if ($terminalId) {
            cookie()->queue(cookie()->forever('pos_terminal_id', (string) $terminalId));
        }

        try {
            $shift = $open($data, $user, $store);
        } catch (ShiftAlreadyOpen $e) {
            return $this->jsonOrError(
                $request,
                $e->getMessage(),
                route('admin.shifts.show', $e->existing),
            );
        } catch (\App\Exceptions\TerminalShiftOpen $e) {
            return $this->jsonOrError($request, $e->getMessage());
        } catch (\App\Exceptions\TradingDayNotOpen $e) {
            return $this->jsonOrError($request, $e->getMessage());
        }

        return $this->jsonOrRedirect(
            $request,
            __('shifts.flash.opened', ['number' => '#'.$shift->id]),
            route('admin.shifts.show', $shift),
        );
    }

    /* ── Close ──────────────────────────────────────────────────── */

    public function closeForm(Request $request, Shift $shift): View|RedirectResponse
    {
        $this->authorize('close', $shift);

        if (! $shift->isOpen()) {
            return redirect()
                ->route('admin.shifts.show', $shift)
                ->with('error', __('shifts.errors.not_open'));
        }

        $totals = ($this->compute)($shift);

        return view('admin.shifts.close', [
            'shift'           => $shift,
            'totals'          => $totals,
            'varianceReasons' => ShiftVarianceReason::query()->active()->ordered()->get(['id', 'code', 'name']),
            'denominations'   => \App\Support\Denominations::forActiveCurrency(),
        ]);
    }

    public function close(CloseShiftRequest $request, Shift $shift, CloseShift $close): JsonResponse|RedirectResponse
    {
        try {
            $shift = $close($shift, $request->validated(), $request->user());
        } catch (VarianceReasonRequired $e) {
            return $this->jsonOrError($request, $e->getMessage());
        }

        return $this->jsonOrRedirect(
            $request,
            __('shifts.flash.closed', ['number' => '#'.$shift->id]),
            route('admin.shifts.show', $shift),
        );
    }

    /* ── X-report (live, printable) ─────────────────────────────── */

    public function xReport(Shift $shift): View
    {
        $this->authorize('view', $shift);

        $shift->load(['store:id,name,currency_code', 'cashier:id,name']);

        return view('admin.shifts.x-report', [
            'shift'  => $shift,
            'totals' => ($this->compute)($shift),
        ]);
    }

    /**
     * Dual-format Z-report print payload (HTML + ESC/POS) for the print
     * bridge — same contract as the sale receipt payload. The cashier /
     * admin "Print Z-report" button fetches this and hands it to the
     * bridge (WebUSB → browser-print).
     */
    public function zReportPayload(Shift $shift, PrepareZReportPayload $prepare): JsonResponse
    {
        $this->authorize('view', $shift);

        return response()->json(($prepare)($shift, current_terminal()));
    }

    /* ── Show ───────────────────────────────────────────────────── */

    public function show(Shift $shift): View
    {
        $this->authorize('view', $shift);

        $shift->load(['store:id,name,currency_code', 'cashier:id,name', 'forceCloser:id,name']);

        $totals = ($this->compute)($shift);

        $entries = $shift->cashDrawerEntries()
            ->with('createdBy:id,name')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('admin.shifts.show', [
            'shift'   => $shift,
            'totals'  => $totals,
            'entries' => $entries,
        ]);
    }

    /* ── Close Day (trading-day wrapper) ───────────────────────────
       Distinct from closing an individual employee's shift above —
       gated on `shifts.close_others` since ending the business day is
       a bigger call than closing your own till. Only reachable once
       every shift under the trading day is already closed. */

    public function closeDayForm(Request $request, \App\Models\TradingDay $tradingDay, \App\Actions\Shifts\ComputeTradingDayTotals $compute): View|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('shifts.close_others', (int) $tradingDay->store_id), 403);

        if (! $tradingDay->isOpen()) {
            return redirect()->route('admin.shifts.index')->with('error', __('shifts.day_errors.already_closed'));
        }

        $tradingDay->load(['shifts.cashier:id,name', 'store:id,name', 'terminal:id,name,code']);

        return view('admin.shifts.close-day', [
            'tradingDay' => $tradingDay,
            'totals'     => $compute($tradingDay),
        ]);
    }

    public function closeDay(Request $request, \App\Models\TradingDay $tradingDay, \App\Actions\Shifts\CloseTradingDay $close): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('shifts.close_others', (int) $tradingDay->store_id), 403);

        try {
            $tradingDay = $close($tradingDay, $request->user());
        } catch (\App\Exceptions\TradingDayNotClosable $e) {
            return $this->jsonOrError($request, $e->getMessage());
        }

        return $this->jsonOrRedirect(
            $request,
            __('shifts.day_flash.closed'),
            route('admin.shifts.index'),
        );
    }

    /**
     * Dual-format print payload for the day-close report — same
     * contract as `zReportPayload()` above, rolled up across every
     * shift under the trading day.
     */
    public function dayReportPayload(Request $request, \App\Models\TradingDay $tradingDay, \App\Actions\Hardware\PrepareDayReportPayload $prepare): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('shifts.close_others', (int) $tradingDay->store_id), 403);

        return response()->json(($prepare)($tradingDay));
    }

    /* ── Cash drawer entries ────────────────────────────────────── */

    public function recordCashDrawerEntry(
        RecordCashDrawerEntryRequest $request,
        Shift $shift,
        RecordCashDrawerEntry $record,
    ): JsonResponse|RedirectResponse {
        try {
            $record($shift, $request->validated(), $request->user());
        } catch (ShiftNotOpen $e) {
            return $this->jsonOrError($request, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->jsonOrError($request, $e->getMessage());
        }

        $msg = match ((string) $request->input('type')) {
            CashDrawerEntry::TYPE_PAY_IN              => __('cash_drawer.flash.pay_in_recorded'),
            CashDrawerEntry::TYPE_PAY_OUT             => __('cash_drawer.flash.pay_out_recorded'),
            CashDrawerEntry::TYPE_DRAWER_OPEN_NO_SALE => __('cash_drawer.flash.drawer_opened_no_sale'),
            default                                   => __('cash_drawer.flash.recorded'),
        };

        return $this->jsonOrRedirect($request, $msg, route('admin.shifts.show', $shift));
    }
}
