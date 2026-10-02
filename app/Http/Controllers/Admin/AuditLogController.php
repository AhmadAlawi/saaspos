<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Admin → Audit Log. Every create/update/delete written by
 * {@see \App\Models\Concerns\Auditable} across the audited models
 * (Product, PriceRule, User, Store, …) — who changed what, and when.
 *
 * Super-admin only, ad-hoc `abort_unless` (this app has no dedicated
 * super-admin middleware — same pattern as
 * {@see SystemHealthController::clearSampleData()}), since this page
 * reads across every store and every user's edits, not gated by the
 * normal per-permission system.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->is_super_admin, 403);

        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();
        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $model  = (string) $request->query('model', '');
        $userId = $request->integer('user_id') ?: null;
        $event  = (string) $request->query('event', '');
        $search = trim((string) $request->query('search', ''));

        $models = AuditLog::query()->distinct()->orderBy('auditable_type')->pluck('auditable_type');

        $rows = AuditLog::query()
            ->with('user:id,name')
            ->whereBetween('created_at', [$from, $to])
            ->when($model !== '', fn ($q) => $q->where('auditable_type', $model))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(in_array($event, ['created', 'updated', 'deleted'], true), fn ($q) => $q->where('event', $event))
            ->when($search !== '', fn ($q) => $q->where('auditable_id', $search))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'rows'    => $rows,
            'models'  => $models,
            'users'   => User::query()->orderBy('name')->get(['id', 'name']),
            'model'   => $model,
            'userId'  => $userId,
            'event'   => $event,
            'search'  => $search,
            'from'    => $from,
            'to'      => $to,
        ]);
    }
}
