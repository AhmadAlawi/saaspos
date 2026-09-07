<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Accounting\LockFiscalPeriod;
use App\Actions\Accounting\RunYearEndClose;
use App\Actions\Accounting\UnlockFiscalPeriod;
use App\Http\Controllers\Controller;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Services\Accounting\FiscalPeriodResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fiscal years + periods (docs/features/accounting.md §4). Lists the years and
 * their monthly periods, locks / unlocks a period, and runs the year-end close.
 * Viewing needs `accounting.view`; locking / unlocking / closing have their own
 * permissions.
 */
class FiscalPeriodController extends Controller
{
    public function __construct(
        private LockFiscalPeriod $lock,
        private UnlockFiscalPeriod $unlock,
        private RunYearEndClose $close,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('accounting.view'), 403);

        // Make sure the current year exists so there's always something to show,
        // and remember which period today falls in for a subtle "current" marker.
        $current = app(FiscalPeriodResolver::class)->forDate(CarbonImmutable::today());

        $years = FiscalYear::query()
            ->with(['periods' => fn ($q) => $q->orderBy('start_date')])
            ->orderByDesc('start_date')
            ->get();

        return view('admin.accounting.periods.index', [
            'years'           => $years,
            'currentPeriodId' => $current->id,
        ]);
    }

    public function lockPeriod(Request $request, FiscalPeriod $period): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('accounting.lock_period'), 403);

        ($this->lock)($period);

        return $this->done($request, __('accounting.periods.locked'));
    }

    public function unlockPeriod(Request $request, FiscalPeriod $period): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('accounting.unlock_period'), 403);

        try {
            ($this->unlock)($period);
        } catch (\RuntimeException $e) {
            return $this->failed($request, 'period', $e->getMessage());
        }

        return $this->done($request, __('accounting.periods.unlocked'));
    }

    public function closeYear(Request $request, FiscalYear $year): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('accounting.year_end_close'), 403);

        try {
            ($this->close)($year);
        } catch (\RuntimeException $e) {
            return $this->failed($request, 'close', $e->getMessage());
        }

        return $this->done($request, __('accounting.periods.closed'));
    }

    /** Success: JSON message (client reloads to show new state) or a flash redirect. */
    private function done(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message);
    }

    /** Guard failure: 422 JSON message or a redirect with the field error. */
    private function failed(Request $request, string $key, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors([$key => $message]);
    }
}
