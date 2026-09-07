<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\CancelStockTransfer;
use App\Actions\Inventory\CreateStockTransfer;
use App\Actions\Inventory\DispatchStockTransfer;
use App\Actions\Inventory\ExportStockTransfers;
use App\Actions\Inventory\ReceiveStockTransfer;
use App\Actions\Inventory\UpdateStockTransfer;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StockTransferRequest;
use App\Models\StockTransfer;
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
 * Stock Transfers — draft → dispatch → receive document flow.
 * See `docs/features/inventory.md` §5 for the status rules.
 */
class StockTransferController extends Controller
{
    use RendersDataTableRows;
    use RespondsJsonOrRedirect;

    /**
     * Transfers — server-paginated. Filters (from/to store / status / date range
     * / search) + paging round-trip to {@see rows()} via the generic
     * `stockTransfersPage` (aliased salesIndexPage) factory. Restricted users
     * see only transfers touching a store they can reach.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockTransfer::class);

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery($request)->paginate($perPage);
        $summary   = $this->summaryFor($request);

        // The from/to store filters may have been dropped for a restricted user
        // (a store they can't reach) — reflect the effective values in the form.
        [$fromStoreId, $toStoreId] = $this->scopedStoreFilters($request);

        return view('admin.inventory.transfers.index', [
            'transfers'    => $paginator->getCollection(),
            'total'        => $paginator->total(),
            'perPage'      => $perPage,
            'totalPages'   => max(1, $paginator->lastPage()),
            'summaryCards' => $this->summaryCards($summary),
            'stores'       => accessible_stores(),
            'filters'      => [
                'fromStoreId' => $fromStoreId,
                'toStoreId'   => $toStoreId,
                'status'      => (string) $request->query('status', ''),
                'from'        => $request->query('from'),
                'to'          => $request->query('to'),
                'q'           => trim((string) $request->query('q', '')),
            ],
        ]);
    }

    /** One page of transfer rows as an HTML fragment plus summary meta. */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.inventory.transfers._rows', 'transfers', [
            'summary' => $this->summaryFor($request),
        ]);
    }

    /**
     * From/to store filters clamped to what the (possibly restricted) user may
     * reach, plus the accessible-store id set (null = super admin, no clamp).
     *
     * @return array{0: ?int, 1: ?int, 2: ?array<int, int>}
     */
    private function scopedStoreFilters(Request $request): array
    {
        $user        = $request->user();
        $restrictIds = ($user && ! $user->is_super_admin) ? $user->accessibleStoreIds() : null;

        $fromStoreId = $request->integer('from_store_id') ?: null;
        $toStoreId   = $request->integer('to_store_id') ?: null;

        if ($restrictIds !== null) {
            if ($fromStoreId && ! in_array($fromStoreId, $restrictIds, true)) $fromStoreId = null;
            if ($toStoreId   && ! in_array($toStoreId,   $restrictIds, true)) $toStoreId   = null;
        }

        return [$fromStoreId, $toStoreId, $restrictIds];
    }

    /** @return Builder<StockTransfer> */
    private function filteredBase(Request $request): Builder
    {
        [$fromStoreId, $toStoreId, $restrictIds] = $this->scopedStoreFilters($request);

        $status = (string) $request->query('status', '');
        $from   = $request->query('from');
        $to     = $request->query('to');
        $q      = trim((string) $request->query('q', ''));

        $validStatuses = [
            StockTransfer::STATUS_DRAFT,
            StockTransfer::STATUS_IN_TRANSIT,
            StockTransfer::STATUS_RECEIVED,
            StockTransfer::STATUS_CANCELLED,
        ];

        return StockTransfer::query()
            ->when($restrictIds !== null, fn ($qb) => $qb->where(function ($w) use ($restrictIds) {
                $w->whereIn('from_store_id', $restrictIds)->orWhereIn('to_store_id', $restrictIds);
            }))
            ->when($fromStoreId, fn ($qb) => $qb->where('from_store_id', $fromStoreId))
            ->when($toStoreId,   fn ($qb) => $qb->where('to_store_id', $toStoreId))
            ->when(in_array($status, $validStatuses), fn ($qb) => $qb->where('status', $status))
            ->when($from, fn ($qb) => $qb->whereDate('transfer_date', '>=', $from))
            ->when($to,   fn ($qb) => $qb->whereDate('transfer_date', '<=', $to))
            ->when($q !== '', fn ($qb) => $qb->where('number', 'like', "%{$q}%"));
    }

    /** @return Builder<StockTransfer> */
    private function listQuery(Request $request): Builder
    {
        return $this->filteredBase($request)
            ->with(['fromStore:id,name', 'toStore:id,name', 'creator:id,name'])
            ->withCount('items')
            ->orderByDesc('id');
    }

    /**
     * @return array{count:string, in_transit:string, received:string}
     */
    private function summaryFor(Request $request): array
    {
        $row = $this->filteredBase($request)
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = '".StockTransfer::STATUS_IN_TRANSIT."' THEN 1 ELSE 0 END), 0) as in_transit")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = '".StockTransfer::STATUS_RECEIVED."' THEN 1 ELSE 0 END), 0) as received")
            ->first();

        return [
            'count'      => number_format((int) ($row->cnt ?? 0)),
            'in_transit' => number_format((int) ($row->in_transit ?? 0)),
            'received'   => number_format((int) ($row->received ?? 0)),
        ];
    }

    /**
     * @param  array{count:string, in_transit:string, received:string}  $summary
     * @return array<int, array<string, mixed>>
     */
    private function summaryCards(array $summary): array
    {
        return [
            ['label' => __('inventory.transfers.summary.count'),      'value' => $summary['count'],      'key' => 'count'],
            ['label' => __('inventory.transfers.summary.in_transit'), 'value' => $summary['in_transit'], 'key' => 'in_transit', 'tone' => 'warning'],
            ['label' => __('inventory.transfers.summary.received'),   'value' => $summary['received'],   'key' => 'received', 'tone' => 'positive'],
        ];
    }

    public function create(): View
    {
        $this->authorize('create', StockTransfer::class);

        $transfer = new StockTransfer([
            'from_store_id' => current_store_id() ?: default_store_id(),
            'transfer_date' => now()->toDateString(),
            'status'        => StockTransfer::STATUS_DRAFT,
        ]);
        $transfer->setRelation('items', collect());

        return view('admin.inventory.transfers.edit', $this->editorPayload($transfer));
    }

    public function store(StockTransferRequest $request, CreateStockTransfer $create): JsonResponse|RedirectResponse
    {
        $this->authorize('create', StockTransfer::class);

        $transfer = $create($request->persistedAttributes(), $request->user());

        return $this->jsonOrRedirect(
            $request,
            __('inventory.transfers.flash.created', ['number' => $transfer->number]),
            route('admin.inventory.transfers.show', $transfer),
        );
    }

    public function edit(StockTransfer $stockTransfer): View
    {
        $this->authorize('update', $stockTransfer);
        abort_unless($stockTransfer->isDraft(), 403);

        $stockTransfer->load([
            'items.product:id,sku,name,unit_id',
            'items.product.unit:id,code',
            'items.variant:id,sku,attributes',
        ]);

        return view('admin.inventory.transfers.edit', $this->editorPayload($stockTransfer));
    }

    public function update(StockTransferRequest $request, StockTransfer $stockTransfer, UpdateStockTransfer $update): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $stockTransfer);
        abort_unless($stockTransfer->isDraft(), 403);

        $update($stockTransfer, $request->persistedAttributes(), $request->user());

        return $this->jsonOrRedirect(
            $request,
            __('inventory.transfers.flash.updated', ['number' => $stockTransfer->number]),
            route('admin.inventory.transfers.show', $stockTransfer),
        );
    }

    public function show(StockTransfer $stockTransfer): View
    {
        $this->authorize('view', $stockTransfer);

        $stockTransfer->load([
            'fromStore:id,name', 'toStore:id,name',
            'creator:id,name', 'updater:id,name',
            'items.product:id,sku,name,unit_id',
            'items.product.unit:id,code,name',
            'items.variant:id,sku,attributes',
        ]);

        return view('admin.inventory.transfers.show', [
            'transfer' => $stockTransfer,
        ]);
    }

    public function dispatch(StockTransfer $stockTransfer, DispatchStockTransfer $dispatch, Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('dispatch', $stockTransfer);

        try {
            $dispatch($stockTransfer, $request->user());
        } catch (\Throwable $e) {
            return $this->jsonOrError(
                $request,
                __('inventory.transfers.flash.dispatch_failed', ['error' => $e->getMessage()]),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('inventory.transfers.flash.dispatched', ['number' => $stockTransfer->number]),
            route('admin.inventory.transfers.show', $stockTransfer),
        );
    }

    public function receive(StockTransfer $stockTransfer, ReceiveStockTransfer $receive, Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('receive', $stockTransfer);

        $request->validate([
            'items'                         => ['required', 'array'],
            'items.*.id'                    => ['required', 'integer'],
            'items.*.received_quantity'     => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $receive($stockTransfer, $request->input('items', []), $request->user());
        } catch (\Throwable $e) {
            return $this->jsonOrError(
                $request,
                __('inventory.transfers.flash.receive_failed', ['error' => $e->getMessage()]),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('inventory.transfers.flash.received', ['number' => $stockTransfer->number]),
            route('admin.inventory.transfers.show', $stockTransfer),
        );
    }

    public function cancel(StockTransfer $stockTransfer, CancelStockTransfer $cancel, Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('cancel', $stockTransfer);

        try {
            $cancel($stockTransfer, $request->user());
        } catch (\Throwable $e) {
            return $this->jsonOrError(
                $request,
                __('inventory.transfers.flash.cancel_failed', ['error' => $e->getMessage()]),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('inventory.transfers.flash.cancelled', ['number' => $stockTransfer->number]),
            route('admin.inventory.transfers.show', $stockTransfer),
        );
    }

    public function destroy(Request $request, StockTransfer $stockTransfer): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $stockTransfer);
        abort_unless($stockTransfer->isDraft(), 403);

        $number = $stockTransfer->number;
        $stockTransfer->items()->delete();
        $stockTransfer->delete();

        return $this->jsonOrRedirect(
            $request,
            __('inventory.transfers.flash.deleted', ['number' => $number]),
            route('admin.inventory.transfers.index'),
        );
    }

    /**
     * Returns available (on-hand) quantities for a list of products in a store.
     * Accepts: { store_id, items: [{ product_id, variant_id }] }
     * Returns: [{ product_id, variant_id, quantity }]
     */
    public function productStock(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

        $request->validate([
            'store_id'             => ['required', 'integer'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'integer'],
            'items.*.variant_id'   => ['nullable', 'integer'],
        ]);

        $storeId = (int) $request->input('store_id');
        $items   = $request->input('items');

        $result = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : null;

            $level = StockLevel::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->when(
                    $variantId,
                    fn ($q) => $q->where('variant_id', $variantId),
                    fn ($q) => $q->whereNull('variant_id')
                )
                ->value('quantity');

            $result[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity'   => $level !== null ? (string) $level : '0.0000',
            ];
        }

        return response()->json($result);
    }

    /**
     * Variant-aware product search — same shape as StockAdjustmentController.
     * Returns `[{ value, label, sku, product_id, variant_id }]`.
     */
    /**
     * Resolve a scanned barcode to a single product / variant for the transfer
     * editor's scan-to-add. Same contract as the adjustment scan endpoint.
     */
    public function scan(Request $request, ScannedProductResolver $resolver): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

        $barcode = trim((string) $request->query('barcode', ''));
        if ($barcode === '') {
            return response()->json(['message' => __('inventory.transfers.scan.empty')], 422);
        }

        $row = $resolver->resolve($barcode);
        if ($row === null) {
            return response()->json([
                'message' => __('inventory.transfers.scan.not_found', ['barcode' => $barcode]),
            ], 404);
        }

        return response()->json($row);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

        $q = trim((string) $request->query('q', ''));

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
            ->get(['v.id as variant_id', 'p.id as product_id', 'p.name as product_name', 'v.sku as variant_sku', 'v.attributes as variant_attributes', 'p.sku as product_sku']);

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
            ->get(['id', 'name', 'sku']);

        $rows = $variants->map(fn ($v) => [
            'value'      => "{$v->product_id}-{$v->variant_id}",
            'label'      => "{$v->product_name} — ".$this->variantLabel($v->variant_attributes, $v->variant_sku),
            'sku'        => $v->variant_sku ?: $v->product_sku,
            'product_id' => (int) $v->product_id,
            'variant_id' => (int) $v->variant_id,
        ])->concat($simple->map(fn ($p) => [
            'value'      => (string) $p->id,
            'label'      => $p->name,
            'sku'        => $p->sku,
            'product_id' => (int) $p->id,
            'variant_id' => null,
        ]))->take(25)->values();

        return response()->json($rows);
    }

    private function variantLabel(?string $attributesJson, ?string $fallbackSku): string
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

    public function export(Request $request, ExportStockTransfers $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    /** @return array<string, mixed> */
    private function editorPayload(StockTransfer $transfer): array
    {
        return [
            'transfer'       => $transfer,
            'stores'         => accessible_stores(),
            'fromStoreId'    => $transfer->from_store_id ?? (current_store_id() ?: default_store_id()),
            'stockLevelsUrl' => route('admin.inventory.transfers.product-stock'),
        ];
    }
}
