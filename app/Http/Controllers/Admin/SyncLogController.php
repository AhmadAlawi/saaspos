<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sync log — owner-facing audit trail of every cashier-complete
 * round-trip. Written by `SaleController::complete()` on every POST
 * that carries a `local_uuid`. Surfaces:
 *
 *   - successes (most rows)
 *   - failures (server-side rejections — bad payload, etc.)
 *   - conflicts (insufficient-stock, expired-batch, etc.)
 *
 * Slice 4 ships READ-ONLY visibility. Resolution actions
 * (acknowledge / void / create-adjustment) are a polish slice — most
 * conflicts are transient (cashier retries and it works) so the value
 * of action buttons is lower than the value of just *seeing* what
 * happened during an outage.
 *
 * Permission: `sync.log.view`.
 */
class SyncLogController extends Controller
{
    use RendersDataTableRows;

    /**
     * Sync log — server-paginated. The result tabs are page-nav links; the
     * date range + search self-manage via the generic `syncLogPage`
     * (aliased salesIndexPage) factory and paging round-trips to {@see rows()}.
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('sync.log.view'), 403);

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery($request)->paginate($perPage);
        $summary   = $this->summaryFor($request);

        // Tab badges stay GLOBAL (unfiltered) — they show the overall breakdown.
        $counts = [
            'all'      => SyncLog::query()->count(),
            'success'  => SyncLog::query()->success()->count(),
            'failed'   => SyncLog::query()->failed()->count(),
            'conflict' => SyncLog::query()->conflict()->count(),
        ];

        return view('admin.sync-log.index', [
            'logs'         => $paginator->getCollection(),
            'total'        => $paginator->total(),
            'perPage'      => $perPage,
            'totalPages'   => max(1, $paginator->lastPage()),
            'summaryCards' => $this->summaryCards($summary),
            'counts'       => $counts,
            'filters'      => [
                'result' => (string) $request->query('result', ''),
                'from'   => $request->query('from'),
                'to'     => $request->query('to'),
                'q'      => trim((string) $request->query('q', '')),
            ],
        ]);
    }

    /** One page of sync-log rows as an HTML fragment plus summary meta. */
    public function rows(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('sync.log.view'), 403);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.sync-log._rows', 'logs', [
            'summary' => $this->summaryFor($request),
        ]);
    }

    /**
     * Active filter set (result tab + date range + search).
     *
     * @return Builder<SyncLog>
     */
    private function filteredBase(Request $request): Builder
    {
        $result = (string) $request->query('result', '');
        $from   = $request->query('from');
        $to     = $request->query('to');
        $q      = trim((string) $request->query('q', ''));

        return SyncLog::query()
            ->when(\in_array($result, ['success', 'failed', 'conflict'], true),
                fn ($qb) => $qb->where('result', $result))
            ->when($from, fn ($qb) => $qb->whereDate('synced_at', '>=', $from))
            ->when($to,   fn ($qb) => $qb->whereDate('synced_at', '<=', $to))
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('local_uuid', 'like', "%{$q}%")
                  ->orWhere('result_message', 'like', "%{$q}%");
            }));
    }

    /** @return Builder<SyncLog> */
    private function listQuery(Request $request): Builder
    {
        return $this->filteredBase($request)
            ->with(['user:id,name'])
            ->orderByDesc('synced_at')
            ->orderByDesc('id');
    }

    /**
     * Summary-card totals over the filtered set, as display-ready strings.
     *
     * @return array{total:string, success:string, conflict:string, failed:string}
     */
    private function summaryFor(Request $request): array
    {
        $row = $this->filteredBase($request)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COALESCE(SUM(CASE WHEN result = 'success' THEN 1 ELSE 0 END), 0) as success")
            ->selectRaw("COALESCE(SUM(CASE WHEN result = 'conflict' THEN 1 ELSE 0 END), 0) as conflict")
            ->selectRaw("COALESCE(SUM(CASE WHEN result = 'failed' THEN 1 ELSE 0 END), 0) as failed")
            ->first();

        return [
            'total'    => number_format((int) ($row->total ?? 0)),
            'success'  => number_format((int) ($row->success ?? 0)),
            'conflict' => number_format((int) ($row->conflict ?? 0)),
            'failed'   => number_format((int) ($row->failed ?? 0)),
        ];
    }

    /**
     * @param  array{total:string, success:string, conflict:string, failed:string}  $summary
     * @return array<int, array<string, mixed>>
     */
    private function summaryCards(array $summary): array
    {
        return [
            ['label' => __('sync_log.summary.total'),    'value' => $summary['total'],    'key' => 'total'],
            ['label' => __('sync_log.summary.success'),  'value' => $summary['success'],  'key' => 'success', 'tone' => 'positive'],
            ['label' => __('sync_log.summary.conflict'), 'value' => $summary['conflict'], 'key' => 'conflict', 'tone' => 'warning'],
            ['label' => __('sync_log.summary.failed'),   'value' => $summary['failed'],   'key' => 'failed', 'tone' => 'danger'],
        ];
    }

    public function show(Request $request, SyncLog $syncLog): View
    {
        abort_unless($request->user()?->hasPermission('sync.log.view'), 403);

        // If this log corresponds to a successful sale we can link to
        // it; pull the row so the show page can render a small KPI strip.
        $sale = $syncLog->synced_entity_id
            ? Sale::query()->find($syncLog->synced_entity_id)
            : null;

        return view('admin.sync-log.show', [
            'log'  => $syncLog->load('user:id,name'),
            'sale' => $sale,
        ]);
    }
}
