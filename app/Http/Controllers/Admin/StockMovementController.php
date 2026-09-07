<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Stock movements ledger — read-only audit trail of every change. Filters
 * by store / type / date range / product search. Gated by `products.view`.
 */
class StockMovementController extends Controller
{
    use RendersDataTableRows;

    /** Movement types the system writes; surfaced to the filter dropdown. */
    public const TYPES = [
        'opening', 'adjustment', 'sale', 'return',
        'purchase', 'transfer_out', 'transfer_in',
    ];

    /**
     * Stock movements ledger — server-paginated. The full ledger grows without
     * bound (every sale/adjustment/transfer writes a row), so the previous
     * "load everything" render was the worst offender. Filters (store / type /
     * date range / product search) + paging round-trip to {@see rows()} via the
     * generic `stockMovementsPage` (aliased salesIndexPage) factory.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockMovement::class);

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery($request)->paginate($perPage);
        $summary   = $this->summaryFor($request);

        return view('admin.inventory.movements.index', [
            'movements'    => $paginator->getCollection(),
            'total'        => $paginator->total(),
            'perPage'      => $perPage,
            'totalPages'   => max(1, $paginator->lastPage()),
            'summaryCards' => $this->summaryCards($summary),
            'stores'       => accessible_stores(),
            'types'        => self::TYPES,
            'filters'      => [
                'storeId' => enforce_store_access($request->integer('store_id') ?: current_store_id()),
                'type'    => (string) $request->query('type', ''),
                'from'    => $request->query('from'),
                'to'      => $request->query('to'),
                'q'       => trim((string) $request->query('q', '')),
            ],
        ]);
    }

    /** One page of ledger rows as an HTML fragment plus summary meta. */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockMovement::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.inventory.movements._rows', 'movements', [
            'summary' => $this->summaryFor($request),
        ]);
    }

    /**
     * Active filter set. Store scoping follows the StockLevel convention.
     *
     * @return Builder<StockMovement>
     */
    private function filteredBase(Request $request): Builder
    {
        $storeId = enforce_store_access($request->integer('store_id') ?: current_store_id());
        $type    = (string) $request->query('type', '');
        $from    = $request->query('from');
        $to      = $request->query('to');
        $q       = trim((string) $request->query('q', ''));

        return StockMovement::query()
            ->when($storeId, fn ($qb) => $qb->where('store_id', $storeId))
            ->when($type !== '' && in_array($type, self::TYPES, true), fn ($qb) => $qb->where('type', $type))
            ->when($from, fn ($qb) => $qb->whereDate('created_at', '>=', $from))
            ->when($to,   fn ($qb) => $qb->whereDate('created_at', '<=', $to))
            ->when($q !== '', function ($qb) use ($q) {
                $qb->whereHas('product', fn ($pq) =>
                    $pq->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%"));
            });
    }

    /** @return Builder<StockMovement> */
    private function listQuery(Request $request): Builder
    {
        return $this->filteredBase($request)
            ->with(['product:id,sku,name', 'variant:id,sku,attributes', 'store:id,name', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Summary-card totals over the FULL filtered set, as display-ready strings.
     *
     * @return array{count:string, in:string, out:string}
     */
    private function summaryFor(Request $request): array
    {
        $agg = $this->filteredBase($request)
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity_delta > 0 THEN quantity_delta ELSE 0 END), 0) as in_sum')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity_delta < 0 THEN quantity_delta ELSE 0 END), 0) as out_sum')
            ->first();

        $trimQty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.') ?: '0';

        return [
            'count' => number_format((int) ($agg->cnt ?? 0)),
            'in'    => $trimQty($agg->in_sum ?? 0),
            'out'   => $trimQty(abs((float) ($agg->out_sum ?? 0))),
        ];
    }

    /**
     * @param  array{count:string, in:string, out:string}  $summary
     * @return array<int, array<string, mixed>>
     */
    private function summaryCards(array $summary): array
    {
        return [
            ['label' => __('inventory.movements.summary.count'), 'value' => $summary['count'], 'key' => 'count'],
            ['label' => __('inventory.movements.summary.in'),    'value' => $summary['in'],    'key' => 'in', 'tone' => 'positive'],
            ['label' => __('inventory.movements.summary.out'),   'value' => $summary['out'],   'key' => 'out', 'tone' => 'danger'],
        ];
    }
}
