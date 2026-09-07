<?php

namespace App\Http\Controllers\Admin;

use App\Actions\TaxComponents\CreateTaxComponent;
use App\Actions\TaxComponents\DeleteTaxComponent;
use App\Actions\TaxComponents\ExportTaxComponents;
use App\Actions\TaxComponents\UpdateTaxComponent;
use App\Exceptions\TaxComponentInUse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaxComponentRequest;
use App\Models\TaxComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tax components admin (Settings → Tax → Components). Single-page
 * master-detail with the system-wide AJAX swap pattern (mirrors
 * StockAdjustmentReasonController / DrugScheduleController). Components
 * are atomic taxes — code/name/rate. Groups bundle them.
 *
 * Gated by `settings.tax.view` (read) / `settings.tax.update` (write).
 * The delete guard refuses if the component participates in any group;
 * users must remove it from groups first.
 */
class TaxComponentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('settings.tax.view');

        $rows       = TaxComponent::query()->ordered()->get();
        $selectedId = $request->integer('selected');
        $isNew      = $request->boolean('new');
        $selected   = $selectedId ? $rows->firstWhere('id', $selectedId) : null;

        return view('admin.settings.tax.components.index', [
            'rows'     => $rows,
            'selected' => $selected,
            'isNew'    => $isNew,
        ]);
    }

    public function store(TaxComponentRequest $request, CreateTaxComponent $create): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.tax.update');

        $row     = $create($request->persistedAttributes());
        $message = __('tax.components.flash.created', ['name' => $row->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($row->id, $message);
        }

        return redirect()
            ->route('admin.settings.tax.components.index', ['selected' => $row->id])
            ->with('success', $message);
    }

    public function update(TaxComponentRequest $request, TaxComponent $taxComponent, UpdateTaxComponent $update): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.tax.update');

        $update($taxComponent, $request->persistedAttributes());
        $message = __('tax.components.flash.updated', ['name' => $taxComponent->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($taxComponent->id, $message);
        }

        return redirect()
            ->route('admin.settings.tax.components.index', ['selected' => $taxComponent->id])
            ->with('success', $message);
    }

    public function destroy(Request $request, TaxComponent $taxComponent, DeleteTaxComponent $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.tax.update');

        $name = $taxComponent->name;

        try {
            $delete($taxComponent);
        } catch (TaxComponentInUse $e) {
            $msg = __('tax.components.errors.in_use', ['count' => $e->count, 'name' => $name]);
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()
                ->route('admin.settings.tax.components.index', ['selected' => $taxComponent->id])
                ->with('error', $msg);
        }

        $message = __('tax.components.flash.deleted', ['name' => $name]);

        if ($request->wantsJson()) {
            return $this->freshListJson(null, $message);
        }

        return redirect()
            ->route('admin.settings.tax.components.index')
            ->with('success', $message);
    }

    /** Returns the tax-group count so the client can warn before deactivating. */
    public function deactivateCheck(TaxComponent $taxComponent): JsonResponse
    {
        $this->authorize('settings.tax.edit');

        return response()->json([
            'count' => $taxComponent->groups()->count(),
        ]);
    }

    /** Returns all active + inactive components as JSON — used by the
     *  Tax Groups page to refresh the component picker after a CRUD op. */
    public function list(): JsonResponse
    {
        $this->authorize('settings.tax.view');

        $components = TaxComponent::query()->ordered()->get();

        return response()->json([
            'components' => $components->map(fn (TaxComponent $c) => [
                'id'        => $c->id,
                'code'      => $c->code,
                'name'      => $c->name,
                'rate'      => (string) $c->rate,
                'is_active' => (bool) $c->is_active,
            ])->values(),
        ]);
    }

    public function export(Request $request, ExportTaxComponents $export): \Symfony\Component\HttpFoundation\StreamedResponse
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
        $rows = TaxComponent::query()->ordered()->get();

        $listHtml = view('admin.settings.tax.components._list', ['rows' => $rows])->render();

        $rowsForJs = $rows->map(fn (TaxComponent $r) => [
            'id'         => $r->id,
            'code'       => $r->code,
            'name'       => $r->name,
            'rate'       => (string) $r->rate,
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
