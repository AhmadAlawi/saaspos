<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Categories\CreateCategory;
use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\ExportCategories;
use App\Actions\Categories\ReorderCategories;
use App\Actions\Categories\UpdateCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Models\TaxGroup;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Master-detail CRUD for product categories.
 *
 * The list view (/admin/categories) is a two-column page: sortable list
 * on the left + sticky editor on the right. The editor opens via
 * `?selected={id}` / `?new=1` on first paint; from there everything is
 * driven by Alpine state, and saves/deletes happen over AJAX. Each
 * save/delete returns the *whole* re-rendered list HTML so the client
 * can swap it in place — single code path, no row-vs-list branching.
 *
 * Drag-to-reorder still POSTs to /admin/categories/reorder; that one
 * remains pure JSON since the order changes apply to the same DOM the
 * user just dropped.
 */
class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Category::class);

        $categories  = Category::query()
            ->withCount('products')
            ->ordered()
            ->get();
        $orderedRows = $this->treeOrdered($categories);

        $selectedId = $request->integer('selected');
        $isNew      = $request->boolean('new');

        $selected = $selectedId
            ? $categories->firstWhere('id', $selectedId)
            : null;

        $taxGroups = $this->activeTaxGroups();

        return view('admin.categories.index', [
            'categories'    => $orderedRows,
            'selected'      => $selected,
            'isNew'         => $isNew,
            'parents'       => $orderedRows,
            'colorChoices'  => CategoryRequest::COLOR_CHOICES,
            'taxGroups'     => $taxGroups,
            'taxGroupNames' => $taxGroups->pluck('name', 'id'),
        ]);
    }

    /**
     * JSON search for the remote category picker (product form). Returns
     * up to 25 active categories matching the query on name, each with
     * its default `tax_group_id` so the picker can pre-fill the product's
     * Tax rule when a category is chosen.
     *
     * Shape: `[{ value, label, tax_group_id }]`.
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $q = trim((string) $request->query('q', ''));

        $categories = Category::query()
            ->active()
            ->when($q !== '', fn ($qb) => $qb->where('name', 'like', "%{$q}%"))
            ->ordered()
            ->limit(25)
            ->get(['id', 'name', 'tax_group_id']);

        return response()->json(
            $categories->map(fn (Category $c) => [
                'value'        => (string) $c->id,
                'label'        => $c->name,
                'tax_group_id' => $c->tax_group_id ? (string) $c->tax_group_id : '',
            ])->all()
        );
    }

    public function store(CategoryRequest $request, CreateCategory $create): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = ($create)($request->persistedAttributes());
        $message  = __('categories.flash.created', ['name' => $category->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($category->id, $message);
        }

        return redirect()
            ->route('admin.categories.index', ['selected' => $category->id])
            ->with('success', $message);
    }

    public function update(CategoryRequest $request, Category $category, UpdateCategory $update): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $category);

        ($update)($category, $request->persistedAttributes());
        $message = __('categories.flash.updated', ['name' => $category->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($category->id, $message);
        }

        return redirect()
            ->route('admin.categories.index', ['selected' => $category->id])
            ->with('success', $message);
    }

    public function destroy(Request $request, Category $category, DeleteCategory $delete): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $category);

        $name = $category->name;
        $replacementId = $request->integer('replacement_id') ?: null;

        try {
            ($delete)($category, $replacementId);
        } catch (\App\Exceptions\CategoryNotDeletable $e) {
            $msg = __('categories.errors.'.$e->reason, ['name' => $name]);
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()
                ->route('admin.categories.index', ['selected' => $category->id])
                ->with('error', $msg);
        }

        $message = $delete->movedCount > 0
            ? __('categories.flash.deleted_with_move', [
                'name'   => $name,
                'count'  => $delete->movedCount,
                'target' => $delete->targetName,
            ])
            : __('categories.flash.deleted', ['name' => $name]);

        if ($request->wantsJson()) {
            return $this->freshListJson(null, $message);
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('success', $message);
    }

    /**
     * Returns the number of products assigned to this category. The client
     * calls this before toggling `is_active` to false so it can warn the
     * operator when linked products exist.
     *
     * Shape: `{ count: int }`.
     */
    public function deactivateCheck(Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        return response()->json([
            'count' => $category->products()->count(),
        ]);
    }

    /**
     * Pre-delete probe — the client calls this when the user opens the
     * confirm dialog so it can show "X products will be moved to Y"
     * and offer a searchable picker. Lightweight: just counts, doesn't
     * lock anything.
     *
     * Shape: `{ count, default: { id, name }|null }`.
     */
    public function deleteInfo(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $default = Category::default();

        return response()->json([
            'count'   => \App\Actions\Categories\DeleteCategory::liveProductCount($category),
            'default' => $default && $default->id !== $category->id
                ? ['id' => $default->id, 'name' => $default->name]
                : null,
        ]);
    }

    /**
     * Server-side search for the "move products to…" picker on the
     * delete dialog. Returns up to 25 categories matching the query,
     * excluding the row being deleted.
     */
    public function searchForReplacement(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $q       = trim((string) $request->query('q', ''));
        $exclude = $request->integer('exclude') ?: null;

        $rows = Category::query()
            ->when($q !== '', fn ($qb) => $qb->where('name', 'like', "%{$q}%"))
            ->when($exclude, fn ($qb) => $qb->where('id', '!=', $exclude))
            ->ordered()
            ->limit(25)
            ->get(['id', 'name', 'is_default']);

        return response()->json($rows->map(fn (Category $c) => [
            'value'      => (string) $c->id,
            'label'      => $c->is_default ? "{$c->name} (default)" : $c->name,
            'is_default' => (bool) $c->is_default,
        ])->all());
    }

    /**
     * Stream a CSV or XLSX of every category as a file download.
     * Format is picked from `?format=csv|xlsx` (defaults to CSV).
     * Authorization re-uses the policy's `viewAny` gate — anyone who
     * can see the list can export it.
     */
    public function export(Request $request, ExportCategories $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', Category::class);

        $format = $request->query('format', 'csv');
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    /**
     * Persist a new order — and optionally new parent assignments — from
     * SortableJS. Payload (JSON):
     *
     *   { "rows": [
     *       { "id": 3, "parent_id": null },
     *       { "id": 5, "parent_id": 3   },
     *       ...
     *   ] }
     */
    public function reorder(Request $request, ReorderCategories $reorder): JsonResponse
    {
        $this->authorize('reorder', Category::class);

        $data = $request->validate([
            'rows'             => ['required', 'array', 'min:1'],
            'rows.*.id'        => ['required', 'integer'],
            'rows.*.parent_id' => ['nullable', 'integer'],
        ]);

        try {
            ($reorder)($data['rows']);
        } catch (\RuntimeException $e) {
            return response()->json([
                'ok'    => false,
                'error' => 'cycle',
            ], 422);
        }

        // Ship the freshly-rendered tree so the client can swap in place
        // — no page reload needed when a parent change shifts indentation.
        return $this->freshListJson(null, '');
    }

    /* ── Shared helpers ─────────────────────────────────────────── */

    /**
     * Walk parent → child relationships into a single flat collection
     * tagged with `tree_depth`. Used both by the page render and by
     * the AJAX list refresh.
     */
    private function treeOrdered(Collection $categories): Collection
    {
        $byParent    = $categories->groupBy(fn ($c) => $c->parent_id ?? 0);
        $orderedRows = collect();
        $walk = function ($parentKey, int $depth) use (&$walk, $byParent, $orderedRows) {
            $children = $byParent->get($parentKey) ?? collect();
            foreach ($children as $cat) {
                $cat->tree_depth = $depth;
                $orderedRows->push($cat);
                $walk($cat->id, $depth + 1);
            }
        };
        $walk(0, 0);
        return $orderedRows;
    }

    /** Active tax groups the editor's "Tax rule" dropdown picks from. */
    private function activeTaxGroups(): EloquentCollection
    {
        return TaxGroup::query()
            ->active()
            ->ordered()
            ->withSum('components', 'rate')
            ->get(['id', 'name']);
    }

    /**
     * Build the JSON the client uses to refresh the list after any
     * save/delete. Contains:
     *   - message     toast text
     *   - id          the row the editor should switch to (null = close)
     *   - list_html   freshly-rendered <li>'s for `.cat-list`
     *   - rows        flat array the client uses to refresh its in-memory
     *                 `rowsById` lookup
     */
    private function freshListJson(?int $selectedId, string $message): JsonResponse
    {
        $categories  = Category::query()
            ->withCount('products')
            ->ordered()
            ->get();
        $orderedRows = $this->treeOrdered($categories);
        $taxGroups   = $this->activeTaxGroups();

        $listHtml = view('admin.categories._list', [
            'categories'    => $orderedRows,
            'taxGroupNames' => $taxGroups->pluck('name', 'id'),
        ])->render();

        $rows = $orderedRows->map(fn ($c) => [
            'id'             => $c->id,
            'name'           => $c->name,
            'parent_id'      => $c->parent_id,
            'color'          => $c->color,
            'tax_group_id'   => $c->tax_group_id,
            'is_active'      => (bool) $c->is_active,
            'is_default'     => (bool) $c->is_default,
            'products_count' => (int) ($c->products_count ?? 0),
            'updated_at'     => $c->updated_at?->diffForHumans(),
        ])->values();

        return response()->json([
            'ok'        => true,
            'message'   => $message,
            'id'        => $selectedId,
            'list_html' => $listHtml,
            'rows'      => $rows,
        ]);
    }
}
