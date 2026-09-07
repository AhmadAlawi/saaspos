<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\CreateStockAdjustment;
use App\Actions\Inventory\DeleteStockAdjustment;
use App\Actions\Inventory\ExportStockAdjustments;
use App\Actions\Inventory\PostStockAdjustment;
use App\Actions\Inventory\UpdateStockAdjustment;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StockAdjustmentRequest;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentReason;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Barcodes\ScannedProductResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Stock Adjustments — draft → post document flow. Drafts are editable;
 * posted documents are view-only and live in the ledger via
 * `stock_movements`. See `docs/features/inventory.md` §4 for the rules.
 */
class StockAdjustmentController extends Controller
{
    use RendersDataTableRows;
    use RespondsJsonOrRedirect;

    /**
     * Adjustments — server-paginated. Filters (store / status / date range /
     * search) + paging round-trip to {@see rows()} via the generic
     * `stockAdjustmentsPage` (aliased salesIndexPage) factory. Store-scoped.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery($request)->paginate($perPage);
        $summary   = $this->summaryFor($request);

        return view('admin.inventory.adjustments.index', [
            'adjustments'  => $paginator->getCollection(),
            'total'        => $paginator->total(),
            'perPage'      => $perPage,
            'totalPages'   => max(1, $paginator->lastPage()),
            'summaryCards' => $this->summaryCards($summary),
            'stores'       => accessible_stores(),
            'filters'      => [
                'storeId' => enforce_store_access($request->integer('store_id') ?: current_store_id()),
                'status'  => (string) $request->query('status', ''),
                'from'    => $request->query('from'),
                'to'      => $request->query('to'),
                'q'       => trim((string) $request->query('q', '')),
            ],
        ]);
    }

    /** One page of adjustment rows as an HTML fragment plus summary meta. */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.inventory.adjustments._rows', 'adjustments', [
            'summary' => $this->summaryFor($request),
        ]);
    }

    /** @return Builder<StockAdjustment> */
    private function filteredBase(Request $request): Builder
    {
        $storeId = enforce_store_access($request->integer('store_id') ?: current_store_id());
        $status  = (string) $request->query('status', '');
        $from    = $request->query('from');
        $to      = $request->query('to');
        $q       = trim((string) $request->query('q', ''));

        return StockAdjustment::query()
            ->when($storeId, fn ($qb) => $qb->where('store_id', $storeId))
            ->when($status === 'draft' || $status === 'posted', fn ($qb) => $qb->where('status', $status))
            ->when($from, fn ($qb) => $qb->whereDate('adjustment_date', '>=', $from))
            ->when($to,   fn ($qb) => $qb->whereDate('adjustment_date', '<=', $to))
            ->when($q !== '', fn ($qb) => $qb->where('number', 'like', "%{$q}%"));
    }

    /** @return Builder<StockAdjustment> */
    private function listQuery(Request $request): Builder
    {
        return $this->filteredBase($request)
            ->with([
                'store:id,name',
                'reasonCode:id,name,code',
                'creator:id,name',
                // The Items column peeks its lines inline (cell-peek) — eager
                // loaded as three queries total, not N+1.
                'items:id,stock_adjustment_id,product_id,variant_id,quantity_delta',
                'items.product:id,name,sku',
                'items.variant', // `label` is an accessor over the whole row
            ])
            ->withCount('items')
            ->orderByDesc('id');
    }

    /**
     * @return array{count:string, posted:string, draft:string}
     */
    private function summaryFor(Request $request): array
    {
        $row = $this->filteredBase($request)
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'posted' THEN 1 ELSE 0 END), 0) as posted")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END), 0) as draft")
            ->first();

        return [
            'count'  => number_format((int) ($row->cnt ?? 0)),
            'posted' => number_format((int) ($row->posted ?? 0)),
            'draft'  => number_format((int) ($row->draft ?? 0)),
        ];
    }

    /**
     * @param  array{count:string, posted:string, draft:string}  $summary
     * @return array<int, array<string, mixed>>
     */
    private function summaryCards(array $summary): array
    {
        return [
            ['label' => __('inventory.adjustments.summary.count'),  'value' => $summary['count'],  'key' => 'count'],
            ['label' => __('inventory.adjustments.summary.posted'), 'value' => $summary['posted'], 'key' => 'posted', 'tone' => 'positive'],
            ['label' => __('inventory.adjustments.summary.draft'),  'value' => $summary['draft'],  'key' => 'draft'],
        ];
    }

    public function create(): View
    {
        $this->authorize('create', StockAdjustment::class);

        $adjustment = new StockAdjustment([
            'store_id'        => current_store_id() ?: default_store_id(),
            'adjustment_date' => now()->toDateString(),
            'status'          => StockAdjustment::STATUS_DRAFT,
        ]);
        $adjustment->setRelation('items', collect());

        return view('admin.inventory.adjustments.edit', $this->editorPayload($adjustment));
    }

    public function store(StockAdjustmentRequest $request, CreateStockAdjustment $create): JsonResponse|RedirectResponse
    {
        $this->authorize('create', StockAdjustment::class);

        $adjustment = $create($request->persistedAttributes(), $request->user());

        return $this->jsonOrRedirect(
            $request,
            __('inventory.adjustments.flash.created', ['number' => $adjustment->number]),
            route('admin.inventory.adjustments.show', $adjustment),
        );
    }

    public function edit(StockAdjustment $stockAdjustment): View
    {
        $this->authorize('update', $stockAdjustment);
        abort_unless($stockAdjustment->isDraft(), 403);

        $stockAdjustment->load(
            'items.product:id,sku,name,unit_id,track_batches',
            'items.product.unit:id,code',
            'items.variant:id,sku,attributes',
            'items.batch:id,batch_number',
        );

        return view('admin.inventory.adjustments.edit', $this->editorPayload($stockAdjustment));
    }

    public function update(StockAdjustmentRequest $request, StockAdjustment $stockAdjustment, UpdateStockAdjustment $update): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $stockAdjustment);
        // State guard — super admins bypass policy via Gate::before, so
        // we re-check the document state here. Posted documents are
        // immutable; corrections go through a counter-adjustment.
        abort_unless($stockAdjustment->isDraft(), 403);

        $update($stockAdjustment, $request->persistedAttributes(), $request->user());

        return $this->jsonOrRedirect(
            $request,
            __('inventory.adjustments.flash.updated', ['number' => $stockAdjustment->number]),
            route('admin.inventory.adjustments.show', $stockAdjustment),
        );
    }

    public function show(StockAdjustment $stockAdjustment): View
    {
        $this->authorize('view', $stockAdjustment);

        $stockAdjustment->load([
            'store:id,name', 'reasonCode:id,name,code',
            'creator:id,name', 'updater:id,name',
            'items.product:id,sku,name,unit_id',
            'items.product.unit:id,code,name',
            'items.variant:id,sku,attributes',
        ]);

        return view('admin.inventory.adjustments.show', [
            'adjustment' => $stockAdjustment,
        ]);
    }

    public function post(StockAdjustment $stockAdjustment, PostStockAdjustment $post, Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('post', $stockAdjustment);
        abort_unless($stockAdjustment->isDraft(), 403);

        try {
            $post($stockAdjustment, $request->user());
        } catch (\Throwable $e) {
            return $this->jsonOrError(
                $request,
                __('inventory.adjustments.flash.post_failed', ['error' => $e->getMessage()]),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('inventory.adjustments.flash.posted', ['number' => $stockAdjustment->number]),
            route('admin.inventory.adjustments.show', $stockAdjustment),
        );
    }

    public function destroy(Request $request, StockAdjustment $stockAdjustment, DeleteStockAdjustment $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $stockAdjustment);
        abort_unless($stockAdjustment->isDraft(), 403);

        $number = $stockAdjustment->number;
        $delete($stockAdjustment);

        return $this->jsonOrRedirect(
            $request,
            __('inventory.adjustments.flash.deleted', ['number' => $number]),
            route('admin.inventory.adjustments.index'),
        );
    }

    /**
     * Current on-hand for a set of (product, variant) lines in a store —
     * powers the "Available" column in the editor. A missing stock-level
     * row reads as 0. Shape: `[{ product_id, variant_id, quantity }]`.
     */
    public function productStock(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $request->validate([
            'store_id'           => ['required', 'integer'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
        ]);

        $storeId = (int) $request->input('store_id');

        $result = [];
        foreach ($request->input('items') as $item) {
            $productId = (int) $item['product_id'];
            $variantId = isset($item['variant_id']) && $item['variant_id'] !== '' ? (int) $item['variant_id'] : null;

            $qty = StockLevel::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->when(
                    $variantId,
                    fn ($q) => $q->where('variant_id', $variantId),
                    fn ($q) => $q->whereNull('variant_id'),
                )
                ->value('quantity');

            $result[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity'   => $qty !== null ? (string) $qty : '0.0000',
            ];
        }

        return response()->json($result);
    }

    /**
     * Variant-aware product search for the line-item picker. Returns
     * the variant rows when a product has variants, the product row
     * itself otherwise. Shape: `[{ value, label, sku }]` where value
     * encodes `productId(-variantId)`.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $q = trim((string) $request->query('q', ''));

        // Variants first — pharmacy / supermarket installs lean heavily
        // on them, so users expect the variant rows to surface directly.
        // Variants have no `name` column; they're described by the
        // `attributes` JSON which the {@see ProductVariant::label}
        // accessor turns into a display string ("Orange · 1L"). We
        // pull attributes too so the picker label reads humanly instead
        // of just showing the SKU — most cashiers don't remember SKUs.
        // Substring-match on the raw JSON text covers attribute search
        // (typing "Orange" finds variants with attributes.color='Orange').
        $variants = DB::table('product_variants as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->whereNull('p.deleted_at')
            ->whereNull('v.deleted_at')
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('p.name', 'like', "%{$q}%")
                      ->orWhere('p.sku', 'like', "%{$q}%")
                      ->orWhere('v.sku', 'like', "%{$q}%")
                      ->orWhere('v.attributes', 'like', "%{$q}%");
                });
            })
            ->orderBy('p.name')
            ->limit(25)
            ->get(['v.id as variant_id', 'p.id as product_id', 'p.name as product_name', 'v.sku as variant_sku', 'v.attributes as variant_attributes', 'p.sku as product_sku', 'p.track_batches']);

        // Simple products (no variants) round out the result set.
        $simple = DB::table('products')
            ->whereNull('deleted_at')
            ->whereNotIn('id', DB::table('product_variants')->select('product_id'))
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'sku', 'track_batches']);

        $rows = $variants->map(fn ($v) => [
            'value'        => "{$v->product_id}-{$v->variant_id}",
            'label'        => "{$v->product_name} — ".$this->variantDisplayLabel($v->variant_attributes, $v->variant_sku),
            'sku'          => $v->variant_sku ?: $v->product_sku,
            'product_id'   => (int) $v->product_id,
            'variant_id'   => (int) $v->variant_id,
            'track_batches'=> (bool) $v->track_batches,
        ])->concat($simple->map(fn ($p) => [
            'value'        => (string) $p->id,
            'label'        => $p->name,
            'sku'          => $p->sku,
            'product_id'   => (int) $p->id,
            'variant_id'   => null,
            'track_batches'=> (bool) $p->track_batches,
        ]))->take(25)->values();

        return response()->json($rows);
    }

    /**
     * Resolve a scanned barcode to a single product / variant so the editor
     * can drop it straight into the line list — no dropdown, no typing.
     *
     * Exact match only (a scan is exact): a variant barcode wins over a product
     * barcode, matching the cashier. Returns the same row shape the picker's
     * `addProduct(row)` consumes, or 404 when nothing carries that barcode.
     */
    public function scan(Request $request, ScannedProductResolver $resolver): JsonResponse
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $barcode = trim((string) $request->query('barcode', ''));
        if ($barcode === '') {
            return response()->json(['message' => __('inventory.adjustments.scan.empty')], 422);
        }

        $row = $resolver->resolve($barcode);
        if ($row === null) {
            return response()->json([
                'message' => __('inventory.adjustments.scan.not_found', ['barcode' => $barcode]),
            ], 404);
        }

        return response()->json($row);
    }

    /**
     * Build the human-readable variant display string from the raw
     * `attributes` JSON column. Mirrors `ProductVariant::label`'s logic:
     * prefer `attributes.label` ("Orange · 1L"), else join the values
     * ("Red · S"), else fall back to the SKU so the picker always has
     * something readable to show.
     */
    private function variantDisplayLabel(?string $attributesJson, ?string $fallbackSku): string
    {
        $sku = (string) ($fallbackSku ?? '');
        if (! $attributesJson) {
            return $sku;
        }
        $attrs = json_decode($attributesJson, true);
        if (! is_array($attrs) || $attrs === []) {
            return $sku;
        }
        if (! empty($attrs['label'])) {
            return (string) $attrs['label'];
        }
        return implode(' · ', array_map('strval', $attrs));
    }

    public function export(Request $request, ExportStockAdjustments $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    /** @return array<string, mixed> */
    /**
     * Available batches for a product at a specific store — used by the
     * adjustment editor's inline batch picker. Accepts store_id from the
     * query string (unlike the cashier endpoint which reads from the session).
     */
    public function batches(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $productId = (int) $request->query('product_id', 0);
        $variantId = $request->query('variant_id');
        $variantId = ($variantId === null || $variantId === '') ? null : (int) $variantId;
        $storeId   = (int) $request->query('store_id', 0);

        if ($productId <= 0 || $storeId <= 0) {
            return response()->json([]);
        }

        $rows = \App\Models\ProductBatch::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->when($variantId !== null, fn ($q) => $q->where('variant_id', $variantId))
            ->when($variantId === null, fn ($q) => $q->whereNull('variant_id'))
            ->whereRaw('quantity > 0')
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'batch_number', 'expiry_date', 'quantity']);

        return response()->json($rows->map(fn ($b) => [
            'id'           => (int) $b->id,
            'batch_number' => (string) $b->batch_number,
            'expiry_date'  => optional($b->expiry_date)->toDateString(),
            'on_hand'      => (string) $b->quantity,
        ]));
    }

    private function editorPayload(StockAdjustment $adjustment): array
    {
        return [
            'adjustment' => $adjustment,
            'stores'     => accessible_stores(),
            'reasons'    => StockAdjustmentReason::query()->active()->ordered()->get(['id', 'code', 'name']),
        ];
    }
}
