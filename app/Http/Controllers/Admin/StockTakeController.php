<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\CreateStockTake;
use App\Actions\Inventory\ExportStockTakes;
use App\Actions\Inventory\PostStockTake;
use App\Actions\Inventory\UpdateStockTake;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StockTakeRequest;
use App\Models\StockTake;
use App\Models\Store;
use App\Services\Barcodes\ScannedProductResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stock Take (cycle count) — draft → post document flow.
 *
 * Drafts are editable; posted documents are view-only and have their
 * variance lines in the ledger via `stock_movements`. See
 * `docs/features/inventory.md` §9 for the rules (slice 4 of the
 * inventory module).
 *
 * Reuses the `products.adjust_stock` permission — a stock take is
 * a flavour of adjustment in terms of authority (you can post one
 * iff you can post a manual adjustment).
 */
class StockTakeController extends Controller
{
    use RespondsJsonOrRedirect;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockTake::class);

        $storeId = enforce_store_access($request->integer('store_id') ?: null);
        $status  = (string) $request->query('status', '');
        $from    = $request->query('from');
        $to      = $request->query('to');
        $q       = trim((string) $request->query('q', ''));

        $takes = StockTake::query()
            ->with(['store:id,name', 'creator:id,name', 'poster:id,name'])
            ->withCount('items')
            ->when($storeId, fn ($qb) => $qb->where('store_id', $storeId))
            ->when(in_array($status, ['draft', 'posted', 'cancelled'], true), fn ($qb) => $qb->where('status', $status))
            ->when($from, fn ($qb) => $qb->whereDate('take_date', '>=', $from))
            ->when($to,   fn ($qb) => $qb->whereDate('take_date', '<=', $to))
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('number', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            }))
            ->orderByDesc('id')
            ->get();

        $summaryCards = [
            ['label' => __('stock_takes.summary.count'),  'value' => number_format($takes->count())],
            ['label' => __('stock_takes.summary.posted'), 'value' => number_format($takes->where('status', 'posted')->count()), 'tone' => 'positive'],
            ['label' => __('stock_takes.summary.draft'),  'value' => number_format($takes->where('status', 'draft')->count())],
        ];

        return view('admin.inventory.stock-takes.index', [
            'takes'        => $takes,
            'summaryCards' => $summaryCards,
            'stores'       => accessible_stores(),
            'filters'      => compact('storeId', 'status', 'from', 'to', 'q'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', StockTake::class);

        return view('admin.inventory.stock-takes.create', [
            'stores' => accessible_stores(),
            'defaultStoreId' => current_store_id() ?: default_store_id(),
        ]);
    }

    public function store(StockTakeRequest $request, CreateStockTake $create): JsonResponse|RedirectResponse
    {
        $this->authorize('create', StockTake::class);

        $take = $create($request->headerAttributes(), $request->user());

        return $this->jsonOrRedirect(
            $request,
            __('stock_takes.flash.created', ['number' => $take->number]),
            route('admin.inventory.stock-takes.edit', $take),
        );
    }

    /**
     * Resolve a scanned barcode to a single product / variant. Unlike the
     * adjustment/transfer editors (which ADD a line), the take editor uses this
     * to FIND the matching count row and bump it — a stock take is a fixed
     * snapshot of the store, so scanning counts an existing row, never adds one.
     */
    public function scan(Request $request, ScannedProductResolver $resolver): JsonResponse
    {
        $this->authorize('viewAny', StockTake::class);

        $barcode = trim((string) $request->query('barcode', ''));
        if ($barcode === '') {
            return response()->json(['message' => __('stock_takes.scan.empty')], 422);
        }

        $row = $resolver->resolve($barcode);
        if ($row === null) {
            return response()->json([
                'message' => __('stock_takes.scan.not_found', ['barcode' => $barcode]),
            ], 404);
        }

        return response()->json($row);
    }

    public function edit(StockTake $stockTake): View
    {
        $this->authorize('update', $stockTake);
        abort_unless($stockTake->isDraft(), 403);

        $stockTake->load([
            'store:id,name',
            'items.product:id,sku,name,unit_id',
            'items.product.unit:id,code',
            'items.variant:id,sku,attributes',
        ]);

        return view('admin.inventory.stock-takes.edit', [
            'take' => $stockTake,
        ]);
    }

    public function update(StockTakeRequest $request, StockTake $stockTake, UpdateStockTake $update): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $stockTake);
        abort_unless($stockTake->isDraft(), 403);

        $update($stockTake, $request->updateAttributes(), $request->user());

        return $this->jsonOrRedirect(
            $request,
            __('stock_takes.flash.updated', ['number' => $stockTake->number]),
            route('admin.inventory.stock-takes.edit', $stockTake),
        );
    }

    public function show(StockTake $stockTake): View
    {
        $this->authorize('view', $stockTake);

        $stockTake->load([
            'store:id,name',
            'creator:id,name',
            'poster:id,name',
            'items.product:id,sku,name,unit_id',
            'items.product.unit:id,code',
            'items.variant:id,sku,attributes',
        ]);

        return view('admin.inventory.stock-takes.show', [
            'take' => $stockTake,
        ]);
    }

    /**
     * Post a draft stock take. Optionally accepts the same items payload
     * as `update()` so the editor's "Post count" button can save the
     * operator's unsaved counts AND post in one click — otherwise the
     * user has to hit Save Progress first, which is friction nobody
     * wants in the middle of a count.
     *
     * If items are present, we run UpdateStockTake first (inside the
     * same outer transaction PostStockTake opens, since UpdateStockTake
     * also opens one — nested transactions in Laravel are handled by
     * savepoints, so this composes cleanly).
     */
    public function post(StockTakeRequest $request, StockTake $stockTake, UpdateStockTake $update, PostStockTake $post): JsonResponse|RedirectResponse
    {
        $this->authorize('post', $stockTake);
        abort_unless($stockTake->isDraft(), 403);

        try {
            $payload = $request->updateAttributes();
            if (!empty($payload['items'] ?? []) || !empty($payload['name']) || !empty($payload['take_date']) || !empty($payload['notes'])) {
                $update($stockTake, $payload, $request->user());
                $stockTake->refresh();
            }
            $post($stockTake, $request->user());
        } catch (\Throwable $e) {
            return $this->jsonOrError(
                $request,
                __('stock_takes.flash.post_failed', ['error' => $e->getMessage()]),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('stock_takes.flash.posted', ['number' => $stockTake->number]),
            route('admin.inventory.stock-takes.show', $stockTake),
        );
    }

    public function destroy(Request $request, StockTake $stockTake): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $stockTake);
        abort_unless($stockTake->isDraft(), 403);

        $number = $stockTake->number;
        $stockTake->delete();

        return $this->jsonOrRedirect(
            $request,
            __('stock_takes.flash.deleted', ['number' => $number]),
            route('admin.inventory.stock-takes.index'),
        );
    }

    public function export(Request $request, ExportStockTakes $export): StreamedResponse
    {
        $this->authorize('viewAny', StockTake::class);

        $format = (string) $request->query('format', 'csv');
        return $export($format);
    }
}
