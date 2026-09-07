<?php

namespace App\Http\Controllers\Admin;

use App\Actions\TaxGroups\CreateTaxGroup;
use App\Actions\TaxGroups\DeleteTaxGroup;
use App\Actions\TaxGroups\ExportTaxGroups;
use App\Actions\TaxGroups\UpdateTaxGroup;
use App\Exceptions\TaxGroupHasProducts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaxGroupRequest;
use App\Models\TaxComponent;
use App\Models\TaxGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tax groups admin (Settings → Tax → Groups).
 *
 * Master-detail like Components, but with a multi-component picker
 * inside the editor. Delete-with-move mirrors Categories: if any product
 * or category references the group, the client offers a move picker
 * (`searchForReplacement` endpoint) before delete.
 *
 * Gated by `settings.tax.view` (read) / `settings.tax.update` (write).
 */
class TaxGroupController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('settings.tax.view');

        $rows       = TaxGroup::query()->withCount(['products', 'categories'])->with('components:id,code,name,rate')->ordered()->get();
        $components = TaxComponent::query()->active()->ordered()->get(['id', 'code', 'name', 'rate']);
        $selectedId = $request->integer('selected');
        $isNew      = $request->boolean('new');
        $selected   = $selectedId ? $rows->firstWhere('id', $selectedId) : null;

        return view('admin.settings.tax.groups.index', [
            'rows'       => $rows,
            'components' => $components,
            'selected'   => $selected,
            'isNew'      => $isNew,
        ]);
    }

    public function store(TaxGroupRequest $request, CreateTaxGroup $create): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.tax.update');

        $row     = $create($request->persistedAttributes(), $request->componentIds());
        $message = __('tax.groups.flash.created', ['name' => $row->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($row->id, $message);
        }

        return redirect()
            ->route('admin.settings.tax.groups.index', ['selected' => $row->id])
            ->with('success', $message);
    }

    public function update(TaxGroupRequest $request, TaxGroup $taxGroup, UpdateTaxGroup $update): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.tax.update');

        $update($taxGroup, $request->persistedAttributes(), $request->componentIds());
        $message = __('tax.groups.flash.updated', ['name' => $taxGroup->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($taxGroup->id, $message);
        }

        return redirect()
            ->route('admin.settings.tax.groups.index', ['selected' => $taxGroup->id])
            ->with('success', $message);
    }

    public function destroy(Request $request, TaxGroup $taxGroup, DeleteTaxGroup $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.tax.update');

        $name          = $taxGroup->name;
        $replacementId = $request->integer('replacement_id') ?: null;

        try {
            $delete($taxGroup, $replacementId);
        } catch (TaxGroupHasProducts $e) {
            $msg = $taxGroup->is_default
                ? __('tax.groups.errors.default_undeletable', ['name' => $name])
                : __('tax.groups.errors.in_use', ['count' => $e->count, 'name' => $name]);
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg, 'count' => $e->count], 422);
            }
            return redirect()
                ->route('admin.settings.tax.groups.index', ['selected' => $taxGroup->id])
                ->with('error', $msg);
        }

        $message = $delete->movedCount > 0
            ? __('tax.groups.flash.deleted_with_move', [
                'name'   => $name,
                'count'  => $delete->movedCount,
                'target' => $delete->targetName,
            ])
            : __('tax.groups.flash.deleted', ['name' => $name]);

        if ($request->wantsJson()) {
            return $this->freshListJson(null, $message);
        }

        return redirect()
            ->route('admin.settings.tax.groups.index')
            ->with('success', $message);
    }

    /** Returns the product count so the client can warn before deactivating. */
    public function deactivateCheck(TaxGroup $taxGroup): JsonResponse
    {
        $this->authorize('settings.tax.edit');

        return response()->json([
            'count' => $taxGroup->products()->count(),
        ]);
    }

    /**
     * Pre-delete probe — the client calls this when the user opens the
     * confirm dialog so it can show "X items will be moved to Y" and
     * offer a searchable picker. Lightweight: just counts + default.
     *
     * Shape: `{ count, default: { id, name }|null, is_default: bool }`.
     */
    public function deleteInfo(TaxGroup $taxGroup): JsonResponse
    {
        $this->authorize('settings.tax.update');

        $default = TaxGroup::default();

        return response()->json([
            'count'      => DeleteTaxGroup::liveReferrerCount($taxGroup),
            'is_default' => (bool) $taxGroup->is_default,
            'default'    => $default && $default->id !== $taxGroup->id
                ? ['id' => $default->id, 'name' => $default->name]
                : null,
        ]);
    }

    /**
     * Server-side search for the "move products to…" picker on the
     * delete dialog. Returns up to 25 groups matching the query,
     * excluding the row being deleted. Mirrors CategoryController.
     */
    public function searchForReplacement(Request $request): JsonResponse
    {
        $this->authorize('settings.tax.view');

        $q       = trim((string) $request->query('q', ''));
        $exclude = $request->integer('exclude') ?: null;

        $rows = TaxGroup::query()
            ->when($q !== '', fn ($qb) => $qb->where('name', 'like', "%{$q}%"))
            ->when($exclude, fn ($qb) => $qb->where('id', '!=', $exclude))
            ->ordered()
            ->limit(25)
            ->get(['id', 'name', 'is_default']);

        return response()->json($rows->map(fn (TaxGroup $g) => [
            'value'      => (string) $g->id,
            'label'      => $g->is_default ? "{$g->name} (default)" : $g->name,
            'is_default' => (bool) $g->is_default,
        ])->all());
    }

    public function export(Request $request, ExportTaxGroups $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('settings.tax.view');

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    private function freshListJson(?int $selectedId, string $message): JsonResponse
    {
        $rows = TaxGroup::query()->withCount(['products', 'categories'])->with('components:id,code,name,rate')->ordered()->get();

        $listHtml = view('admin.settings.tax.groups._list', ['rows' => $rows])->render();

        $rowsForJs = $rows->map(fn (TaxGroup $g) => [
            'id'              => $g->id,
            'code'            => $g->code,
            'name'            => $g->name,
            'classification'  => $g->classification,
            'is_inclusive'    => (bool) $g->is_inclusive,
            'is_default'      => (bool) $g->is_default,
            'is_active'       => (bool) $g->is_active,
            'component_ids'   => $g->components->pluck('id')->map('intval')->values(),
            'component_codes' => $g->components->pluck('code')->values(),
            'rate_total'      => (string) $g->components->sum(fn ($c) => (float) $c->rate),
            'products_count'  => (int) ($g->products_count ?? 0),
            'updated_at'      => $g->updated_at?->diffForHumans(),
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
