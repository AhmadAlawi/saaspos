<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Units\CreateUnit;
use App\Actions\Units\DeleteUnit;
use App\Actions\Units\ExportUnits;
use App\Actions\Units\UpdateUnit;
use App\Exceptions\UnitHasProducts;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UnitRequest;
use App\Models\Unit;
use App\Models\UnitCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master-detail CRUD for measurement units. Same pattern as
 * {@see App\Http\Controllers\Admin\CategoryController}.
 */
class UnitController extends Controller
{
    use RendersDataTableRows;

    /**
     * Units — master-detail. The DOM list is server-paginated; the editor's
     * rowsById + the `baseUnitNames` conversion map cover the full (bounded)
     * set, so a page-2 unit can still show what it converts to on page 1.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Unit::class);

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery(new Request(['per_page' => $perPage]))->paginate($perPage);
        $allUnits  = Unit::query()->ordered()->get();

        $selectedId = $request->integer('selected');
        $isNew      = $request->boolean('new');
        $selected   = $selectedId ? $allUnits->firstWhere('id', $selectedId) : null;

        return view('admin.units.index', [
            'units'      => $paginator->getCollection(),
            'allUnits'   => $allUnits,
            'total'      => $paginator->total(),
            'perPage'    => $perPage,
            'totalPages' => max(1, $paginator->lastPage()),
            'baseUnits'  => $allUnits->whereNull('base_unit_id')->values(),
            'selected'   => $selected,
            'isNew'      => $isNew,
            'categories' => UnitCategory::ordered()->get(),
        ]);
    }

    /** One page of unit rows as an HTML fragment. See {@see RendersDataTableRows}. */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Unit::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        // The conversion label ("converts to X") can reference any unit, so the
        // name map is built over the FULL set, not just this page.
        $baseUnitNames = Unit::query()->ordered()->get()
            ->mapWithKeys(fn (Unit $u) => [$u->id => $u->name.' ('.$u->code.')']);

        return $this->dtRows($paginator, 'admin.units._list', 'units', [], [
            'baseUnitNames' => $baseUnitNames,
        ]);
    }

    /** @return Builder<Unit> */
    private function listQuery(Request $request): Builder
    {
        $query = Unit::query()->with('baseUnit:id,code,name');

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

    public function store(UnitRequest $request, CreateUnit $create): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Unit::class);

        $unit    = ($create)($request->persistedAttributes());
        $message = __('units.flash.created', ['name' => $unit->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($unit->id, $message);
        }

        return redirect()
            ->route('admin.units.index', ['selected' => $unit->id])
            ->with('success', $message);
    }

    public function update(UnitRequest $request, Unit $unit, UpdateUnit $update): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $unit);

        ($update)($unit, $request->persistedAttributes());
        $message = __('units.flash.updated', ['name' => $unit->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($unit->id, $message);
        }

        return redirect()
            ->route('admin.units.index', ['selected' => $unit->id])
            ->with('success', $message);
    }

    public function destroy(Request $request, Unit $unit, DeleteUnit $delete): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $unit);

        $name = $unit->name;

        try {
            ($delete)($unit);
        } catch (UnitHasProducts $e) {
            $key = $e->kind === 'unit' ? 'has_derived_units' : 'has_products';
            $msg = __('units.errors.'.$key, ['count' => $e->count, 'name' => $name]);
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()
                ->route('admin.units.index', ['selected' => $unit->id])
                ->with('error', $msg);
        }

        $message = __('units.flash.deleted', ['name' => $name]);

        if ($request->wantsJson()) {
            return $this->freshListJson(null, $message);
        }

        return redirect()
            ->route('admin.units.index')
            ->with('success', $message);
    }

    /** Returns the product count so the client can warn before deactivating. */
    public function deactivateCheck(Unit $unit): JsonResponse
    {
        $this->authorize('update', $unit);

        return response()->json([
            'count' => \App\Models\Product::where('unit_id', $unit->id)->count(),
        ]);
    }

    public function export(Request $request, ExportUnits $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', Unit::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    private function freshListJson(?int $selectedId, string $message): JsonResponse
    {
        $units = Unit::query()->ordered()->get();

        $listHtml = view('admin.units._list', [
            'units'         => $units,
            'baseUnitNames' => $units->mapWithKeys(fn (Unit $u) => [$u->id => $u->name.' ('.$u->code.')']),
        ])->render();

        $rows = $units->map(fn (Unit $u) => [
            'id'                => $u->id,
            'code'              => $u->code,
            'name'              => $u->name,
            'category'          => $u->category,
            'base_unit_id'      => $u->base_unit_id,
            'conversion_factor' => $u->conversion_factor !== null ? (string) $u->conversion_factor : null,
            'is_active'         => (bool) $u->is_active,
            'updated_at'        => $u->updated_at?->diffForHumans(),
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
