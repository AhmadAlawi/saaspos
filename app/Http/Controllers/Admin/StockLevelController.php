<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\ExportStockLevels;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Models\StockLevel;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Stock levels — per-store list of every product's on-hand quantity.
 * Read-only here, except for the reorder-level override which is the
 * one field the user can edit inline. Real stock changes happen via
 * stock adjustments.
 *
 * Gated by `products.view` (read) / `products.adjust_stock` (the
 * reorder edit). Honors the active store from the store switcher when
 * no explicit `store_id` filter is set.
 */
class StockLevelController extends Controller
{
    use RendersDataTableRows;
    use RespondsJsonOrRedirect;

    /**
     * Stock levels — server-paginated. Filters (store / search) + paging
     * round-trip to {@see rows()} via the generic `stockLevelsPage` (aliased
     * salesIndexPage) factory. The per-row reorder edit is an independent
     * `data-ajax-form` and is unaffected.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockLevel::class);

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery($request)->paginate($perPage);
        $summary   = $this->summaryFor($request);

        return view('admin.inventory.levels.index', [
            'levels'       => $paginator->getCollection(),
            'total'        => $paginator->total(),
            'perPage'      => $perPage,
            'totalPages'   => max(1, $paginator->lastPage()),
            'summaryCards' => $this->summaryCards($summary),
            'stores'       => accessible_stores(),
            'storeId'      => enforce_store_access($request->integer('store_id') ?: current_store_id()),
            'q'            => trim((string) $request->query('q', '')),
            'stockStatus'  => $this->stockStatus($request),
        ]);
    }

    /** One page of stock-level rows as an HTML fragment plus summary meta. */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockLevel::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.inventory.levels._rows', 'levels', [
            'summary' => $this->summaryFor($request),
        ]);
    }

    /** @return Builder<StockLevel> */
    private function filteredBase(Request $request): Builder
    {
        $storeId = enforce_store_access($request->integer('store_id') ?: current_store_id());
        $q       = trim((string) $request->query('q', ''));

        return StockLevel::query()
            ->when($storeId, fn ($qb) => $qb->where('store_id', $storeId))
            ->when($q !== '', function ($qb) use ($q) {
                $qb->whereHas('product', function ($pq) use ($q) {
                    $pq->where('name', 'like', "%{$q}%")
                       ->orWhere('sku', 'like', "%{$q}%")
                       ->orWhere('barcode', 'like', "%{$q}%")
                       ->orWhereHas('barcodes', fn ($b) => $b->where('barcode', 'like', "%{$q}%"));
                });
            })
            ->when($this->stockStatus($request) === 'out', fn ($qb) => $qb->where('quantity', '<=', 0))
            ->when($this->stockStatus($request) === 'in',  fn ($qb) => $qb->where('quantity', '>', 0));
    }

    /** Normalized `stock_status` filter — 'all' | 'in' | 'out'. */
    private function stockStatus(Request $request): string
    {
        $v = (string) $request->query('stock_status', 'all');
        return in_array($v, ['in', 'out'], true) ? $v : 'all';
    }

    /** @return Builder<StockLevel> */
    private function listQuery(Request $request): Builder
    {
        return $this->filteredBase($request)
            ->with(['product:id,sku,name,unit_id,reorder_level', 'product.unit:id,code,name', 'variant:id,sku,attributes', 'store:id,code,name'])
            ->orderByDesc('last_movement_at');
    }

    /**
     * @return array{items:string, units:string, value:string, out:string}
     */
    private function summaryFor(Request $request): array
    {
        $agg = $this->filteredBase($request)
            ->selectRaw('COUNT(*) as items')
            ->selectRaw('COALESCE(SUM(quantity), 0) as units')
            ->selectRaw('COALESCE(SUM(quantity * weighted_average_cost), 0) as value')
            // `out_count`, not `out` — `out` is a reserved word in MariaDB and
            // fails as a bare column alias (SQLSTATE 42000).
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END), 0) as out_count')
            ->first();

        $trimQty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.') ?: '0';

        return [
            'items' => number_format((int) ($agg->items ?? 0)),
            'units' => $trimQty($agg->units ?? 0),
            'value' => format_money($agg->value ?? 0),
            'out'   => number_format((int) ($agg->out_count ?? 0)),
        ];
    }

    /**
     * @param  array{items:string, units:string, value:string, out:string}  $summary
     * @return array<int, array<string, mixed>>
     */
    private function summaryCards(array $summary): array
    {
        return [
            ['label' => __('inventory.levels.summary.items'), 'value' => $summary['items'], 'key' => 'items'],
            ['label' => __('inventory.levels.summary.units'), 'value' => $summary['units'], 'key' => 'units'],
            ['label' => __('inventory.levels.summary.value'), 'value' => $summary['value'], 'key' => 'value'],
            ['label' => __('inventory.levels.summary.out'),   'value' => $summary['out'],   'key' => 'out', 'tone' => 'danger'],
        ];
    }

    public function updateReorderOverride(Request $request, StockLevel $stockLevel): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $stockLevel);

        $data = $request->validate([
            'reorder_level_override' => ['nullable', 'numeric', 'min:0', 'max:9999999.9999'],
        ]);

        $stockLevel->update([
            'reorder_level_override' => $data['reorder_level_override'] === null || $data['reorder_level_override'] === ''
                ? null
                : $data['reorder_level_override'],
        ]);

        return $this->jsonOrRedirect(
            $request,
            __('inventory.levels.flash.reorder_updated'),
            url()->previous() ?: route('admin.inventory.levels.index'),
        );
    }

    public function export(Request $request, ExportStockLevels $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', StockLevel::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }
}
