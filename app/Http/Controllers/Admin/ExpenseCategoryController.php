<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExpenseCategoryRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Expense categories admin — the picklist the expense form draws from.
 * Inline master-detail surface (flat list + sticky editor, AJAX swap),
 * reusing the generic `reasonsPage` Alpine factory — same shape as
 * Return Reasons / Shift Variance Reasons.
 */
class ExpenseCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $rows       = ExpenseCategory::query()->orderBy('name')->get();
        $selectedId = $request->integer('selected');
        $isNew      = $request->boolean('new');
        $selected   = $selectedId ? $rows->firstWhere('id', $selectedId) : null;

        return view('admin.expense-categories.index', [
            'rows'     => $rows,
            'selected' => $selected,
            'isNew'    => $isNew,
        ]);
    }

    public function store(ExpenseCategoryRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', ExpenseCategory::class);

        $row     = ExpenseCategory::create($request->persistedAttributes());
        $message = __('expense_categories.flash.created', ['name' => $row->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($row->id, $message);
        }

        return redirect()->route('admin.expense-categories.index', ['selected' => $row->id])->with('success', $message);
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $expenseCategory);

        $expenseCategory->update($request->persistedAttributes());
        $message = __('expense_categories.flash.updated', ['name' => $expenseCategory->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($expenseCategory->id, $message);
        }

        return redirect()->route('admin.expense-categories.index', ['selected' => $expenseCategory->id])->with('success', $message);
    }

    public function destroy(Request $request, ExpenseCategory $expenseCategory): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $expenseCategory);
        $name = $expenseCategory->name;

        // Keep historical expenses legible — block delete when referenced
        // and tell the operator to deactivate instead.
        $count = Expense::query()->where('category_id', $expenseCategory->id)->count();
        if ($count > 0) {
            $msg = __('expense_categories.errors.in_use', ['count' => $count, 'name' => $name]);
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }

            return redirect()->route('admin.expense-categories.index', ['selected' => $expenseCategory->id])->with('error', $msg);
        }

        $expenseCategory->delete();
        $message = __('expense_categories.flash.deleted', ['name' => $name]);

        if ($request->wantsJson()) {
            return $this->freshListJson(null, $message);
        }

        return redirect()->route('admin.expense-categories.index')->with('success', $message);
    }

    private function freshListJson(?int $selectedId, string $message): JsonResponse
    {
        $rows = ExpenseCategory::query()->orderBy('name')->get();

        $listHtml = view('admin.expense-categories._list', ['rows' => $rows])->render();

        $rowsForJs = $rows->map(fn (ExpenseCategory $r) => [
            'id'        => $r->id,
            'name'      => $r->name,
            'is_active' => (bool) $r->is_active,
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
