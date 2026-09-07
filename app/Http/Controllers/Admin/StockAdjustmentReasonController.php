<?php

namespace App\Http\Controllers\Admin;

use App\Actions\StockAdjustmentReasons\CreateStockAdjustmentReason;
use App\Actions\StockAdjustmentReasons\DeleteStockAdjustmentReason;
use App\Actions\StockAdjustmentReasons\UpdateStockAdjustmentReason;
use App\Exceptions\StockAdjustmentReasonInUse;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StockAdjustmentReasonRequest;
use App\Models\StockAdjustmentReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Adjustment-reasons admin — the picklist for stock adjustments. Single
 * page with the list + side editor; saves and deletes run over AJAX and
 * the server returns the freshly-rendered list HTML so the client swaps
 * it in one go. Mirrors the DrugSchedules / Brands master-detail pattern.
 */
class StockAdjustmentReasonController extends Controller
{
    use RendersDataTableRows;

    /** Master-detail; DOM list server-paginated, editor rowsById covers all. */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockAdjustmentReason::class);

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery(new Request(['per_page' => $perPage]))->paginate($perPage);
        $allRows   = StockAdjustmentReason::query()->ordered()->get();

        $selectedId = $request->integer('selected');
        $isNew      = $request->boolean('new');
        $selected   = $selectedId ? $allRows->firstWhere('id', $selectedId) : null;

        return view('admin.inventory.adjustment-reasons.index', [
            'rows'       => $paginator->getCollection(),
            'allRows'    => $allRows,
            'total'      => $paginator->total(),
            'perPage'    => $perPage,
            'totalPages' => max(1, $paginator->lastPage()),
            'selected'   => $selected,
            'isNew'      => $isNew,
        ]);
    }

    /** One page of reason rows as an HTML fragment. See {@see RendersDataTableRows}. */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockAdjustmentReason::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.inventory.adjustment-reasons._list', 'rows');
    }

    /** @return Builder<StockAdjustmentReason> */
    private function listQuery(Request $request): Builder
    {
        $query = StockAdjustmentReason::query();

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        $column = (string) $request->query('sort', '');
        $dir    = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $map    = ['name' => 'name', 'code' => 'code', 'id' => 'id'];
        if (isset($map[$column])) {
            return $query->orderBy($map[$column], $dir)->orderBy('id', 'desc');
        }

        return $query->ordered();
    }

    public function store(StockAdjustmentReasonRequest $request, CreateStockAdjustmentReason $create): RedirectResponse|JsonResponse
    {
        $this->authorize('create', StockAdjustmentReason::class);

        $row     = $create($request->persistedAttributes());
        $message = __('inventory.reasons.flash.created', ['name' => $row->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($row->id, $message);
        }

        return redirect()
            ->route('admin.inventory.adjustment-reasons.index', ['selected' => $row->id])
            ->with('success', $message);
    }

    public function update(StockAdjustmentReasonRequest $request, StockAdjustmentReason $stockAdjustmentReason, UpdateStockAdjustmentReason $update): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $stockAdjustmentReason);

        $update($stockAdjustmentReason, $request->persistedAttributes());
        $message = __('inventory.reasons.flash.updated', ['name' => $stockAdjustmentReason->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($stockAdjustmentReason->id, $message);
        }

        return redirect()
            ->route('admin.inventory.adjustment-reasons.index', ['selected' => $stockAdjustmentReason->id])
            ->with('success', $message);
    }

    public function destroy(Request $request, StockAdjustmentReason $stockAdjustmentReason, DeleteStockAdjustmentReason $delete): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $stockAdjustmentReason);

        $name = $stockAdjustmentReason->name;

        try {
            $delete($stockAdjustmentReason);
        } catch (StockAdjustmentReasonInUse $e) {
            $msg = __('inventory.reasons.errors.in_use', ['count' => $e->count, 'name' => $name]);
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()
                ->route('admin.inventory.adjustment-reasons.index', ['selected' => $stockAdjustmentReason->id])
                ->with('error', $msg);
        }

        $message = __('inventory.reasons.flash.deleted', ['name' => $name]);

        if ($request->wantsJson()) {
            return $this->freshListJson(null, $message);
        }

        return redirect()
            ->route('admin.inventory.adjustment-reasons.index')
            ->with('success', $message);
    }

    /**
     * Server-rendered list HTML + a structured row snapshot for the
     * Alpine `rowsById` lookup. Returned after every successful save /
     * delete so the client can swap in one go without a reload.
     */
    private function freshListJson(?int $selectedId, string $message): JsonResponse
    {
        $rows = StockAdjustmentReason::query()->ordered()->get();

        $listHtml = view('admin.inventory.adjustment-reasons._list', ['rows' => $rows])->render();

        $rowsForJs = $rows->map(fn (StockAdjustmentReason $r) => [
            'id'         => $r->id,
            'code'       => $r->code,
            'name'       => $r->name,
            'sort_order' => (int) $r->sort_order,
            'is_active'  => (bool) $r->is_active,
            'updated_at' => $r->updated_at?->diffForHumans(),
        ])->values();

        return response()->json([
            'ok'        => true,
            'message'   => $message,
            'id'        => $selectedId,
            'list_html' => $listHtml,
            'rows'      => $rowsForJs,
        ]);
    }
}
