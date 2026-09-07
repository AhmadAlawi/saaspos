<?php

namespace App\Http\Controllers\Admin;

use App\Actions\UnitCategories\CreateUnitCategory;
use App\Actions\UnitCategories\DeleteUnitCategory;
use App\Actions\UnitCategories\ExportUnitCategories;
use App\Actions\UnitCategories\UpdateUnitCategory;
use App\Exceptions\UnitCategoryInUse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UnitCategoryRequest;
use App\Models\UnitCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', \App\Models\Unit::class);

        $rows       = UnitCategory::ordered()->get();
        $selectedId = $request->integer('selected');
        $isNew      = $request->boolean('new');
        $selected   = $selectedId ? $rows->firstWhere('id', $selectedId) : null;

        return view('admin.units.categories.index', [
            'rows'     => $rows,
            'selected' => $selected,
            'isNew'    => $isNew,
        ]);
    }

    public function store(UnitCategoryRequest $request, CreateUnitCategory $create): JsonResponse|RedirectResponse
    {
        $this->authorize('create', \App\Models\Unit::class);

        $row     = $create($request->persistedAttributes());
        $message = __('unit_categories.flash.created', ['name' => $row->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($row->id, $message);
        }

        return redirect()
            ->route('admin.units.categories.index', ['selected' => $row->id])
            ->with('success', $message);
    }

    public function update(UnitCategoryRequest $request, UnitCategory $unitCategory, UpdateUnitCategory $update): JsonResponse|RedirectResponse
    {
        $this->authorize('update', new \App\Models\Unit());

        $update($unitCategory, $request->persistedAttributes());
        $message = __('unit_categories.flash.updated', ['name' => $unitCategory->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($unitCategory->id, $message);
        }

        return redirect()
            ->route('admin.units.categories.index', ['selected' => $unitCategory->id])
            ->with('success', $message);
    }

    public function destroy(Request $request, UnitCategory $unitCategory, DeleteUnitCategory $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', new \App\Models\Unit());

        $name = $unitCategory->name;

        try {
            $delete($unitCategory);
        } catch (UnitCategoryInUse $e) {
            $msg = __('unit_categories.errors.in_use', ['count' => $e->count, 'name' => $name]);
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()
                ->route('admin.units.categories.index', ['selected' => $unitCategory->id])
                ->with('error', $msg);
        }

        $message = __('unit_categories.flash.deleted', ['name' => $name]);

        if ($request->wantsJson()) {
            return $this->freshListJson(null, $message);
        }

        return redirect()
            ->route('admin.units.categories.index')
            ->with('success', $message);
    }

    public function deactivateCheck(UnitCategory $unitCategory): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Unit::class);
        return response()->json([
            'count' => \App\Models\Unit::where('category', $unitCategory->slug)->count(),
        ]);
    }

    public function export(Request $request, ExportUnitCategories $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', \App\Models\Unit::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    private function freshListJson(?int $selectedId, string $message): JsonResponse
    {
        $rows     = UnitCategory::ordered()->get();
        $listHtml = view('admin.units.categories._list', ['rows' => $rows])->render();
        $rowsJs   = $rows->map(fn (UnitCategory $r) => [
            'id'         => $r->id,
            'name'       => $r->name,
            'slug'       => $r->slug,
            'sort_order' => $r->sort_order,
            'is_active'  => (bool) $r->is_active,
            'updated_at' => $r->updated_at?->diffForHumans(),
        ])->values();

        return response()->json([
            'ok'        => true,
            'message'   => $message,
            'id'        => $selectedId,
            'list_html' => $listHtml,
            'rows'      => $rowsJs,
        ]);
    }
}
