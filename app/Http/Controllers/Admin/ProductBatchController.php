<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\DeleteProductBatch;
use App\Actions\Inventory\ExportBatches;
use App\Actions\Inventory\RestoreProductBatch;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Product Batches admin — read-only list of every batch the inventory
 * module knows about. Batches themselves are created/incremented via
 * `ReceivePurchase` and decremented via `RecordStockMovement`; this
 * controller is the visibility surface for owners + pharmacists:
 *
 *   - which batches are live (qty > 0)
 *   - which are expiring soon (configurable window, default 30 days)
 *   - which are expired (and therefore non-sellable when the
 *     `block_expired_batch_sale` company setting is on)
 *
 * Permission: `products.view` — same as the stock-levels page.
 *
 * The one write path is {@see destroy()}: archiving a spent batch, gated on
 * `products.delete_batch`. Batches are still never created or edited here.
 */
class ProductBatchController extends Controller
{
    use RendersDataTableRows;
    use RespondsJsonOrRedirect;

    /**
     * Batches — server-paginated. Status TABS are page-nav links; store / days /
     * search self-manage via the generic `batchesPage` (aliased salesIndexPage)
     * factory and paging round-trips to {@see rows()}.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $storeId   = enforce_store_access($request->integer('store_id') ?: null);
        $productId = $request->integer('product_id') ?: null;
        $status    = (string) $request->query('status', '');
        $days      = $this->days($request);
        $q         = trim((string) $request->query('q', ''));

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery($request)->paginate($perPage);

        // Tab counters (store+product scoped, no status/search) so the tallies
        // stay stable while the user switches status tabs.
        $base = ProductBatch::query()
            ->when($storeId,   fn ($qb) => $qb->where('store_id',   $storeId))
            ->when($productId, fn ($qb) => $qb->where('product_id', $productId));

        $counts = [
            'all'           => (clone $base)->count(),
            'live'          => (clone $base)->live()->count(),
            'expiring_soon' => (clone $base)->expiringWithin($days)->count(),
            'expired'       => (clone $base)->expired()->count(),
            // Archived batches are soft-deleted, so every count above already
            // excludes them — this tab is the only way back to one.
            'archived'      => (clone $base)->onlyTrashed()->count(),
        ];

        return view('admin.inventory.batches.index', [
            'batches'      => $paginator->getCollection(),
            'total'        => $paginator->total(),
            'perPage'      => $perPage,
            'totalPages'   => max(1, $paginator->lastPage()),
            'summaryCards' => $this->summaryCards($counts),
            'stores'       => accessible_stores(),
            'counts'       => $counts,
            'filters'      => compact('storeId', 'productId', 'status', 'days', 'q'),
        ]);
    }

    /** One page of batch rows as an HTML fragment plus summary meta. */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.inventory.batches._rows', 'batches', [
            'summary' => $this->summaryFor($request),
        ]);
    }

    /** Clamp the expiring-soon window to a sane range (default 30 days). */
    private function days(Request $request): int
    {
        $days = (int) $request->query('days', 30);

        return ($days <= 0 || $days > 365) ? 30 : $days;
    }

    /** @return Builder<ProductBatch> */
    private function listQuery(Request $request): Builder
    {
        $storeId   = enforce_store_access($request->integer('store_id') ?: null);
        $productId = $request->integer('product_id') ?: null;
        $status    = (string) $request->query('status', '');
        $days      = $this->days($request);
        $q         = trim((string) $request->query('q', ''));

        $query = ProductBatch::query()
            ->with(['store:id,name', 'product:id,sku,name', 'variant:id,sku'])
            ->when($storeId,   fn ($qb) => $qb->where('store_id',   $storeId))
            ->when($productId, fn ($qb) => $qb->where('product_id', $productId))
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('batch_number', 'like', "%{$q}%")
                  ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%"));
            }))
            // Nulls (no expiry) last; nearest expiry first.
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('id');

        if ($status === 'live')          $query->live();
        if ($status === 'expiring_soon') $query->expiringWithin($days);
        if ($status === 'expired')       $query->expired();
        // The one tab that looks past the soft-delete scope — without it an
        // archived batch would be unreachable from the UI entirely.
        if ($status === 'archived')      $query->onlyTrashed();

        return $query;
    }

    /**
     * Summary cards mirror the store+product-scoped tab counters (total /
     * expiring-soon / expired), so they stay consistent with the tabs.
     *
     * @return array{all:string, expiring_soon:string, expired:string}
     */
    private function summaryFor(Request $request): array
    {
        $storeId   = enforce_store_access($request->integer('store_id') ?: null);
        $productId = $request->integer('product_id') ?: null;
        $days      = $this->days($request);

        $base = ProductBatch::query()
            ->when($storeId,   fn ($qb) => $qb->where('store_id',   $storeId))
            ->when($productId, fn ($qb) => $qb->where('product_id', $productId));

        return [
            'all'           => number_format((clone $base)->count()),
            'expiring_soon' => number_format((clone $base)->expiringWithin($days)->count()),
            'expired'       => number_format((clone $base)->expired()->count()),
        ];
    }

    /**
     * @param  array{all:int|string, expiring_soon:int|string, expired:int|string}  $counts
     * @return array<int, array<string, mixed>>
     */
    private function summaryCards(array $counts): array
    {
        return [
            ['label' => __('batches.summary.total'),         'value' => number_format((int) $counts['all']),           'key' => 'all'],
            ['label' => __('batches.summary.expiring_soon'), 'value' => number_format((int) $counts['expiring_soon']), 'key' => 'expiring_soon', 'tone' => 'warning'],
            ['label' => __('batches.summary.expired'),       'value' => number_format((int) $counts['expired']),       'key' => 'expired', 'tone' => 'danger'],
        ];
    }

    public function export(Request $request, ExportBatches $export): StreamedResponse
    {
        $this->authorize('viewAny', Product::class);

        return $export([
            'store_id'   => enforce_store_access($request->integer('store_id') ?: null),
            'product_id' => $request->integer('product_id') ?: null,
            'status'     => (string) $request->query('status', ''),
            'days'       => (int) $request->query('days', 30),
            'format'     => (string) $request->query('format', 'csv'),
        ]);
    }

    /**
     * Archive an empty batch — a soft delete. See {@see DeleteProductBatch} for
     * why this can never be a hard delete (every FK to product_batches is
     * nullOnDelete, so a real delete would blank batch_id on posted history).
     *
     * Three-layer guard, matching the stock-adjustment delete: the policy hides
     * it, the abort_unless backstops a hand-rolled request, and the action
     * re-checks under a row lock in case a sale drained the batch in between.
     */
    public function destroy(Request $request, ProductBatch $batch, DeleteProductBatch $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $batch);
        abort_unless(enforce_store_access($batch->store_id) !== null, 403);

        $number = $batch->batch_number;

        try {
            $delete($batch);
        } catch (RuntimeException $e) {
            // A sale drained (or refilled) the batch between the page render and
            // the click — the action's locked re-check is the authority.
            return $this->jsonOrError(
                $request,
                __('batches.flash.not_empty', ['batch' => $number]),
                route('admin.inventory.batches.index'),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('batches.flash.archived', ['batch' => $number]),
            route('admin.inventory.batches.index'),
        );
    }

    /**
     * Un-archive a batch. Reached only from the Archived tab, whose rows are
     * the sole place a soft-deleted batch is visible — hence the explicit
     * `withTrashed()` binding on the route.
     */
    public function restore(Request $request, int $batch, RestoreProductBatch $restore): JsonResponse|RedirectResponse
    {
        $model = ProductBatch::withTrashed()->findOrFail($batch);

        $this->authorize('restore', $model);
        abort_unless(enforce_store_access($model->store_id) !== null, 403);

        $restore($model);

        return $this->jsonOrRedirect(
            $request,
            __('batches.flash.restored', ['batch' => $model->batch_number]),
            route('admin.inventory.batches.index', ['status' => 'archived']),
        );
    }
}
