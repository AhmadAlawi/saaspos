<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\ExportLowStock;
use App\Http\Controllers\Controller;
use App\Models\StockLevel;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Low-stock report — read-only list of `(store, product, variant)` rows
 * whose on-hand quantity is at or below the **effective reorder
 * threshold**:
 *
 *   effective_threshold = COALESCE(stock_levels.reorder_level_override,
 *                                  products.reorder_level)
 *
 * Rows with no threshold set anywhere are excluded (the system has no
 * opinion about whether they're "low"). Sorted by biggest deficit first
 * so the most urgent items surface at the top. Gated by `products.view`
 * — anyone who can see products can see what's running low.
 */
class LowStockReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockLevel::class);

        // Raw rows first (StockLevel models with `quantity`) so the summary
        // can count out-of-stock before decoration reshapes them.
        $base = $this->query($request)->get();
        $summaryCards = [
            ['label' => __('inventory.low_stock.summary.below'), 'value' => number_format($base->count()), 'tone' => 'warning'],
            ['label' => __('inventory.low_stock.summary.out'),   'value' => number_format($base->where('quantity', '<=', 0)->count()), 'tone' => 'danger'],
        ];

        return view('admin.inventory.low-stock.index', [
            'rows'         => $base->map(fn ($r) => $this->decorate($r)),
            'summaryCards' => $summaryCards,
            'stores'       => accessible_stores(),
            'storeId'      => $this->storeFilter($request),
        ]);
    }

    public function export(Request $request, ExportLowStock $export): StreamedResponse
    {
        $this->authorize('viewAny', StockLevel::class);

        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';
        $rows   = $this->query($request)->get()->map(fn ($r) => $this->decorate($r));

        return $export($rows, $format);
    }

    /**
     * Effective store scope. Defaults to the active store (store-wise —
     * matching the dashboard's Low-stock card) when no filter is submitted;
     * an explicit "All stores" (empty store_id) is honoured for users
     * allowed to see across stores.
     */
    private function storeFilter(Request $request): ?int
    {
        return $request->has('store_id')
            ? enforce_store_access($request->integer('store_id') ?: null)
            : enforce_store_access(current_store_id());
    }

    /** Shared query: low-stock rows with their thresholds + names. */
    private function query(Request $request)
    {
        $storeId = $this->storeFilter($request);

        return StockLevel::query()
            ->from('product_stock_levels as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('product_variants as v', 'v.id', '=', 'l.variant_id')
            ->join('stores as s', 's.id', '=', 'l.store_id')
            ->whereNull('p.deleted_at')
            // Effective threshold: per-store override, falling back to the
            // product default. NULL on both → row excluded.
            ->whereNotNull(\Illuminate\Support\Facades\DB::raw('COALESCE(l.reorder_level_override, p.reorder_level)'))
            ->whereColumn('l.quantity', '<=', \Illuminate\Support\Facades\DB::raw('COALESCE(l.reorder_level_override, p.reorder_level)'))
            ->when($storeId, fn ($q) => $q->where('l.store_id', $storeId))
            ->orderByRaw('(COALESCE(l.reorder_level_override, p.reorder_level) - l.quantity) DESC')
            ->select([
                'l.id', 'l.store_id', 'l.product_id', 'l.variant_id',
                'l.quantity', 'l.reorder_level_override',
                'p.name as product_name', 'p.sku as product_sku', 'p.reorder_level as product_reorder',
                'v.sku as variant_sku', 'v.attributes as variant_attributes',
                's.name as store_name',
            ]);
    }

    /** Turn a DB row into the array shape the view + export both consume. */
    private function decorate(object $r): array
    {
        $threshold = $r->reorder_level_override !== null
            ? (float) $r->reorder_level_override
            : (float) $r->product_reorder;
        $qty       = (float) $r->quantity;

        $variantLabel = null;
        if ($r->variant_id && $r->variant_attributes) {
            $attrs = json_decode($r->variant_attributes, true);
            if (is_array($attrs) && $attrs !== []) {
                $variantLabel = $attrs['label'] ?? implode(' · ', array_map('strval', $attrs));
            }
            $variantLabel = $variantLabel ?: $r->variant_sku;
        }

        return [
            'store'          => $r->store_name,
            'product'        => $r->product_name,
            'variant_label'  => $variantLabel,
            'sku'            => $r->variant_sku ?: $r->product_sku,
            'on_hand'        => $qty,
            'threshold'      => $threshold,
            'deficit'        => $threshold - $qty,
        ];
    }
}
