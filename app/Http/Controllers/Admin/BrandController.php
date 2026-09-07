<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Brands\CreateBrand;
use App\Actions\Brands\DeleteBrand;
use App\Actions\Brands\ExportBrands;
use App\Actions\Brands\UpdateBrand;
use App\Exceptions\BrandNotDeletable;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Master-detail CRUD for product brands. Same pattern as
 * {@see App\Http\Controllers\Admin\CategoryController} — flat list on
 * the left, sticky editor on the right; saves and deletes run over
 * AJAX and the server returns the freshly-rendered list HTML so the
 * client swaps it in one go.
 */
class BrandController extends Controller
{
    use RendersDataTableRows;

    /**
     * Brands list — the DOM list is server-paginated; every save/delete reloads
     * the current page. The side-editor can open ANY brand (including one just
     * created off the current page), so it still receives every row's data as a
     * small lookup map ({@see index()}'s `allBrands`) — only the rendered list
     * (with its per-row directives) is paged.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Brand::class);

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery(new Request(['per_page' => $perPage]))->paginate($perPage);

        // Full set for the editor's rowsById + the `selected`/`initial` hydration.
        // Brands are a bounded lookup, so the lightweight map is cheap; the win
        // is not rendering every row's Alpine directives into the DOM at once.
        $allBrands = Brand::query()->withCount('products')->ordered()->get();

        $selectedId = $request->integer('selected');
        $isNew      = $request->boolean('new');
        $selected   = $selectedId ? $allBrands->firstWhere('id', $selectedId) : null;

        return view('admin.brands.index', [
            'brands'     => $paginator->getCollection(),
            'allBrands'  => $allBrands,
            'total'      => $paginator->total(),
            'perPage'    => $perPage,
            'totalPages' => max(1, $paginator->lastPage()),
            'selected'   => $selected,
            'isNew'      => $isNew,
        ]);
    }

    /**
     * One page of brand rows as an HTML fragment. See {@see RendersDataTableRows}.
     * No `rows` payload — the editor's rowsById is seeded full from the inline
     * page and refreshed from every save/delete response.
     */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Brand::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.brands._list', 'brands');
    }

    /** @return Builder<Brand> */
    private function listQuery(Request $request): Builder
    {
        $query = Brand::query()->withCount('products');

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        // The list's sort menu offers name / id; default is the curated
        // `ordered()` (sort_order, name).
        $column = (string) $request->query('sort', '');
        $dir    = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $map    = ['name' => 'name', 'id' => 'id'];
        if (isset($map[$column])) {
            return $query->orderBy($map[$column], $dir)->orderBy('id', 'desc');
        }

        return $query->ordered();
    }

    /**
     * JSON search for the remote brand picker (product form). Returns up
     * to 25 active brands matching the query on name.
     *
     * Shape: `[{ value, label }]`.
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Brand::class);

        $q = trim((string) $request->query('q', ''));

        $brands = Brand::query()
            ->active()
            ->when($q !== '', fn ($qb) => $qb->where('name', 'like', "%{$q}%"))
            ->ordered()
            ->limit(25)
            ->get(['id', 'name']);

        return response()->json(
            $brands->map(fn (Brand $b) => ['value' => (string) $b->id, 'label' => $b->name])->all()
        );
    }

    public function store(BrandRequest $request, CreateBrand $create): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Brand::class);

        $data = $request->persistedAttributes();
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('brands', 'public');
        }

        $brand   = ($create)($data);
        $message = __('brands.flash.created', ['name' => $brand->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($brand->id, $message);
        }

        return redirect()
            ->route('admin.brands.index', ['selected' => $brand->id])
            ->with('success', $message);
    }

    public function update(BrandRequest $request, Brand $brand, UpdateBrand $update): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $brand);

        $data = $request->persistedAttributes();

        // Three logo lifecycles to handle:
        //   1. file uploaded → store new, drop old
        //   2. logo_remove=1 → drop old, blank the column
        //   3. neither → leave logo_path off the update payload so the
        //      existing file (and column value) stay put. The AJAX
        //      status-toggle PATCH falls into this branch.
        if ($request->hasFile('logo')) {
            $this->deleteStoredLogo($brand);
            $data['logo_path'] = $request->file('logo')->store('brands', 'public');
        } elseif ($request->boolean('logo_remove')) {
            $this->deleteStoredLogo($brand);
            $data['logo_path'] = null;
        }

        ($update)($brand, $data);
        $message = __('brands.flash.updated', ['name' => $brand->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($brand->id, $message);
        }

        return redirect()
            ->route('admin.brands.index', ['selected' => $brand->id])
            ->with('success', $message);
    }

    public function destroy(Request $request, Brand $brand, DeleteBrand $delete): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $brand);

        $name = $brand->name;
        $replacementId = $request->integer('replacement_id') ?: null;

        try {
            ($delete)($brand, $replacementId);
        } catch (BrandNotDeletable $e) {
            $msg = __('brands.errors.'.$e->reason, ['name' => $name]);
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()
                ->route('admin.brands.index', ['selected' => $brand->id])
                ->with('error', $msg);
        }

        $message = $delete->movedCount > 0
            ? __('brands.flash.deleted_with_move', [
                'name'   => $name,
                'count'  => $delete->movedCount,
                'target' => $delete->targetName,
            ])
            : __('brands.flash.deleted', ['name' => $name]);

        if ($request->wantsJson()) {
            return $this->freshListJson(null, $message);
        }

        return redirect()
            ->route('admin.brands.index')
            ->with('success', $message);
    }

    /** Returns the product count so the client can warn before deactivating. */
    public function deactivateCheck(Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        return response()->json([
            'count' => $brand->products()->count(),
        ]);
    }

    /** Pre-delete probe — mirrors CategoryController::deleteInfo. */
    public function deleteInfo(Brand $brand): JsonResponse
    {
        $this->authorize('delete', $brand);
        $default = Brand::default();

        return response()->json([
            'count'   => \App\Actions\Brands\DeleteBrand::liveProductCount($brand),
            'default' => $default && $default->id !== $brand->id
                ? ['id' => $default->id, 'name' => $default->name]
                : null,
        ]);
    }

    /** Server-side search for the "move products to…" picker. */
    public function searchForReplacement(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Brand::class);

        $q       = trim((string) $request->query('q', ''));
        $exclude = $request->integer('exclude') ?: null;

        $rows = Brand::query()
            ->when($q !== '', fn ($qb) => $qb->where('name', 'like', "%{$q}%"))
            ->when($exclude, fn ($qb) => $qb->where('id', '!=', $exclude))
            ->ordered()
            ->limit(25)
            ->get(['id', 'name', 'is_default']);

        return response()->json($rows->map(fn (Brand $b) => [
            'value'      => (string) $b->id,
            'label'      => $b->is_default ? "{$b->name} (default)" : $b->name,
            'is_default' => (bool) $b->is_default,
        ])->all());
    }

    public function export(Request $request, ExportBrands $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', Brand::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    /**
     * Delete the brand's stored logo file (if any) from the public disk.
     * Idempotent — quietly no-ops when the file is already gone or the
     * brand never had one.
     */
    private function deleteStoredLogo(Brand $brand): void
    {
        if (! $brand->logo_path) return;
        if (Storage::disk('public')->exists($brand->logo_path)) {
            Storage::disk('public')->delete($brand->logo_path);
        }
    }

    /**
     * Build the JSON the client uses to refresh the list after any
     * save/delete.
     */
    private function freshListJson(?int $selectedId, string $message): JsonResponse
    {
        $brands = Brand::query()->withCount('products')->ordered()->get();

        $listHtml = view('admin.brands._list', ['brands' => $brands])->render();

        $rows = $brands->map(fn (Brand $b) => [
            'id'          => $b->id,
            'name'        => $b->name,
            'description' => $b->description,
            'logo_url'    => $b->logo_url,
            'is_active'   => (bool) $b->is_active,
            'updated_at'  => $b->updated_at?->diffForHumans(),
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
