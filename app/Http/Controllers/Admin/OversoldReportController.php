<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\ExportOversold;
use App\Http\Controllers\Controller;
use App\Models\StockLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Oversold report — read-only list of `(store, product, variant)` rows
 * whose on-hand quantity has gone **negative** (sold below available stock
 * under the overselling policy — see docs/features/inventory.md).
 *
 * Each row's oversold quantity is `-on_hand`; adding stock (purchase
 * receive or an In adjustment) nets it off automatically, since stock is a
 * running ledger. Sorted most-oversold first. Gated by `products.view`.
 */
class OversoldReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockLevel::class);

        $base = $this->query($request)->get();
        $totalOversold = $base->sum(fn ($r) => -(float) $r->quantity);
        $summaryCards = [
            ['label' => __('inventory.oversold.summary.items'), 'value' => number_format($base->count()), 'tone' => 'danger'],
            ['label' => __('inventory.oversold.summary.units'), 'value' => rtrim(rtrim(number_format($totalOversold, 4, '.', ''), '0'), '.') ?: '0', 'tone' => 'warning'],
        ];

        return view('admin.inventory.oversold.index', [
            'rows'         => $base->map(fn ($r) => $this->decorate($r)),
            'summaryCards' => $summaryCards,
            'stores'       => accessible_stores(),
            'storeId'      => $this->storeFilter($request),
        ]);
    }

    public function export(Request $request, ExportOversold $export): StreamedResponse
    {
        $this->authorize('viewAny', StockLevel::class);

        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';
        $rows   = $this->query($request)->get()->map(fn ($r) => $this->decorate($r));

        return $export($rows, $format);
    }

    /**
     * Effective store scope. Defaults to the active store when no filter is
     * submitted; an explicit "All stores" (empty store_id) is honoured for
     * users allowed to see across stores. Mirrors the low-stock report.
     */
    private function storeFilter(Request $request): ?int
    {
        return $request->has('store_id')
            ? enforce_store_access($request->integer('store_id') ?: null)
            : enforce_store_access(current_store_id());
    }

    /** Shared query: negative-on-hand rows with their names. */
    private function query(Request $request)
    {
        $storeId = $this->storeFilter($request);

        return StockLevel::query()
            ->from('product_stock_levels as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('product_variants as v', 'v.id', '=', 'l.variant_id')
            ->join('stores as s', 's.id', '=', 'l.store_id')
            ->whereNull('p.deleted_at')
            ->where('l.quantity', '<', 0)
            ->when($storeId, fn ($q) => $q->where('l.store_id', $storeId))
            // Most oversold first (quantity is negative, so ascending = deepest).
            ->orderBy('l.quantity')
            ->select([
                'l.id', 'l.store_id', 'l.product_id', 'l.variant_id',
                'l.quantity', 'l.last_movement_at',
                'p.name as product_name', 'p.sku as product_sku',
                'v.sku as variant_sku', 'v.attributes as variant_attributes',
                's.name as store_name',
            ]);
    }

    /** Turn a DB row into the array shape the view + export both consume. */
    private function decorate(object $r): array
    {
        $qty = (float) $r->quantity;

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
            'oversold_by'    => -$qty,
            'last_movement'  => $r->last_movement_at ? format_datetime($r->last_movement_at) : null,
        ];
    }
}
