<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesReportFilters;
use App\Http\Controllers\Controller;
use App\Models\CashierActivityLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cashier activity log, by type — cart edits, discounts, hold/void,
 * drawer kicks, shift open/close, prints, checkout, refunds, login/
 * logout, and client-side JS errors, all in one queryable table
 * ({@see \App\Models\CashierActivityLog}). Same filter-bar/date-range
 * convention as the other `reports.*` screens, plus a `type` filter this
 * report alone needs.
 */
class ActivityLogReportController extends Controller
{
    use ResolvesReportFilters;

    private const TYPES = ['cart', 'discount', 'sale', 'refund', 'drawer', 'shift', 'print', 'auth', 'error'];

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('reports.view_sales'), 403);

        [$from, $to, $storeId, $period] = $this->reportFilters($request);
        $type   = (string) $request->query('type', '');
        $userId = $request->integer('user_id') ?: null;

        $query = CashierActivityLog::query()
            ->with(['user:id,name', 'store:id,name', 'terminal:id,name'])
            ->whereBetween('created_at', [$from, $to->endOfDay()])
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when(in_array($type, self::TYPES, true), fn ($q) => $q->where('type', $type))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('created_at');

        $rows = $query->paginate(50)->withQueryString();

        // By-type counts over the SAME filtered window (minus the type
        // filter itself) — the report's headline breakdown.
        $byType = CashierActivityLog::query()
            ->whereBetween('created_at', [$from, $to->endOfDay()])
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return view('admin.reports.activity-log.index', [
            'rows'    => $rows,
            'byType'  => $byType,
            'types'   => self::TYPES,
            'type'    => $type,
            'userId'  => $userId,
            'from'    => $from,
            'to'      => $to,
            'storeId' => $storeId,
            'period'  => $period,
            'stores'  => Store::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'users'   => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
