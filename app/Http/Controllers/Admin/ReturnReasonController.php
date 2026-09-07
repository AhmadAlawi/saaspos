<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ReturnReasonInUse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReturnReasonRequest;
use App\Models\ReturnReason;
use App\Models\SaleReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Return-reasons admin — the picklist the cashier picks from in the
 * refund form. Single page with list + side editor; saves / deletes /
 * status-toggles all run over AJAX and the server returns the freshly-
 * rendered list HTML so the client swaps it in one go. Mirrors the
 * StockAdjustmentReason / Brand / Unit master-detail pattern. The
 * surface is small enough that CRUD lives inline rather than in
 * separate Action classes.
 */
class ReturnReasonController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ReturnReason::class);

        $rows       = ReturnReason::query()->ordered()->get();
        $selectedId = $request->integer('selected');
        $isNew      = $request->boolean('new');
        $selected   = $selectedId ? $rows->firstWhere('id', $selectedId) : null;

        return view('admin.return-reasons.index', [
            'rows'     => $rows,
            'selected' => $selected,
            'isNew'    => $isNew,
        ]);
    }

    public function store(ReturnReasonRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', ReturnReason::class);

        $row     = ReturnReason::create($request->persistedAttributes());
        $message = __('return_reasons.flash.created', ['name' => $row->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($row->id, $message);
        }
        return redirect()->route('admin.return-reasons.index', ['selected' => $row->id])->with('success', $message);
    }

    public function update(ReturnReasonRequest $request, ReturnReason $returnReason): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $returnReason);

        $returnReason->update($request->persistedAttributes());
        $message = __('return_reasons.flash.updated', ['name' => $returnReason->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($returnReason->id, $message);
        }
        return redirect()->route('admin.return-reasons.index', ['selected' => $returnReason->id])->with('success', $message);
    }

    public function destroy(Request $request, ReturnReason $returnReason): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $returnReason);
        $name = $returnReason->name;

        // Block deletion if any sale_returns row references this reason
        // — receipts in the past would otherwise lose their reason text.
        // Operators can toggle the row inactive instead.
        $count = SaleReturn::query()->where('reason_code_id', $returnReason->id)->count();
        if ($count > 0) {
            $e   = new ReturnReasonInUse($count);
            $msg = __('return_reasons.errors.in_use', ['count' => $e->count, 'name' => $name]);
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()->route('admin.return-reasons.index', ['selected' => $returnReason->id])->with('error', $msg);
        }

        $returnReason->delete();
        $message = __('return_reasons.flash.deleted', ['name' => $name]);

        if ($request->wantsJson()) {
            return $this->freshListJson(null, $message);
        }
        return redirect()->route('admin.return-reasons.index')->with('success', $message);
    }

    /**
     * Server-rendered list HTML + structured row snapshot for the
     * Alpine `rowsById` lookup. Returned after every successful save /
     * delete / toggle so the client can swap in one go without a reload.
     */
    private function freshListJson(?int $selectedId, string $message): JsonResponse
    {
        $rows = ReturnReason::query()->ordered()->get();

        $listHtml = view('admin.return-reasons._list', ['rows' => $rows])->render();

        $rowsForJs = $rows->map(fn (ReturnReason $r) => [
            'id'                  => $r->id,
            'code'                => $r->code,
            'name'                => $r->name,
            'sort_order'          => (int) $r->sort_order,
            'default_restock'     => (bool) $r->default_restock,
            'requires_permission' => (bool) $r->requires_permission,
            'is_active'           => (bool) $r->is_active,
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
