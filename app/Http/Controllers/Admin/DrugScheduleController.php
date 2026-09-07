<?php

namespace App\Http\Controllers\Admin;

use App\Actions\DrugSchedules\CreateDrugSchedule;
use App\Actions\DrugSchedules\DeleteDrugSchedule;
use App\Actions\DrugSchedules\ExportDrugSchedules;
use App\Actions\DrugSchedules\UpdateDrugSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DrugScheduleRequest;
use App\Models\DrugSchedule;
use App\Support\Countries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Drug Schedules admin — flat list with side editor, same pattern as
 * Brands and Units. Reference table read by the Products module for
 * the "Pharmacy schedule" select.
 */
class DrugScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', DrugSchedule::class);

        $rows = DrugSchedule::query()->ordered()->get();

        $selectedId = $request->integer('selected');
        $isNew      = $request->boolean('new');

        $selected = $selectedId ? $rows->firstWhere('id', $selectedId) : null;

        return view('admin.drug-schedules.index', [
            'rows'      => $rows,
            'selected'  => $selected,
            'isNew'     => $isNew,
            'countries' => Countries::all(),
        ]);
    }

    public function store(DrugScheduleRequest $request, CreateDrugSchedule $create): RedirectResponse|JsonResponse
    {
        $this->authorize('create', DrugSchedule::class);

        $row     = ($create)($request->persistedAttributes());
        $message = __('drug_schedules.flash.created', ['name' => $row->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($row->id, $message);
        }

        return redirect()
            ->route('admin.drug-schedules.index', ['selected' => $row->id])
            ->with('success', $message);
    }

    public function update(DrugScheduleRequest $request, DrugSchedule $drugSchedule, UpdateDrugSchedule $update): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $drugSchedule);

        ($update)($drugSchedule, $request->persistedAttributes());
        $message = __('drug_schedules.flash.updated', ['name' => $drugSchedule->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($drugSchedule->id, $message);
        }

        return redirect()
            ->route('admin.drug-schedules.index', ['selected' => $drugSchedule->id])
            ->with('success', $message);
    }

    public function destroy(Request $request, DrugSchedule $drugSchedule, DeleteDrugSchedule $delete): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $drugSchedule);

        $name          = $drugSchedule->name;
        $replacementId = $request->integer('replacement_id') ?: null;

        ($delete)($drugSchedule, $replacementId);

        $message = $delete->movedCount > 0
            ? ($delete->targetName
                ? __('drug_schedules.flash.deleted_with_move', [
                    'name'   => $name,
                    'count'  => $delete->movedCount,
                    'target' => $delete->targetName,
                ])
                : __('drug_schedules.flash.deleted_with_clear', [
                    'name'  => $name,
                    'count' => $delete->movedCount,
                ]))
            : __('drug_schedules.flash.deleted', ['name' => $name]);

        if ($request->wantsJson()) {
            return $this->freshListJson(null, $message);
        }

        return redirect()
            ->route('admin.drug-schedules.index')
            ->with('success', $message);
    }

    /** Returns the product count so the client can warn before deactivating. */
    public function deactivateCheck(DrugSchedule $drugSchedule): JsonResponse
    {
        $this->authorize('update', $drugSchedule);

        return response()->json([
            'count' => \App\Models\Product::where('pharmacy_schedule', $drugSchedule->code)->count(),
        ]);
    }

    /**
     * Pre-delete probe — the client calls this when the user opens the
     * confirm dialog so it can show "X products will be moved" and offer
     * a searchable "move to…" picker. Lightweight: counts only.
     *
     * Shape: `{ count }`. There is no default schedule, so a blank pick
     * clears the schedule from those products.
     */
    public function deleteInfo(DrugSchedule $drugSchedule): JsonResponse
    {
        $this->authorize('delete', $drugSchedule);

        return response()->json([
            'count' => DeleteDrugSchedule::liveProductCount($drugSchedule),
        ]);
    }

    /**
     * Server-side search for the "move products to…" picker on the delete
     * dialog. Returns up to 25 schedules matching the query, excluding the
     * row being deleted.
     *
     * Shape: `[{ value, label }]`.
     */
    public function searchForReplacement(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DrugSchedule::class);

        $q       = trim((string) $request->query('q', ''));
        $exclude = $request->integer('exclude') ?: null;

        $rows = DrugSchedule::query()
            ->when($q !== '', fn ($qb) => $qb->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")))
            ->when($exclude, fn ($qb) => $qb->where('id', '!=', $exclude))
            ->ordered()
            ->limit(25)
            ->get(['id', 'code', 'name']);

        return response()->json($rows->map(fn (DrugSchedule $r) => [
            'value' => (string) $r->id,
            'label' => $r->code ? "{$r->name} ({$r->code})" : $r->name,
        ])->all());
    }

    public function export(Request $request, ExportDrugSchedules $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', DrugSchedule::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    private function freshListJson(?int $selectedId, string $message): JsonResponse
    {
        $rows = DrugSchedule::query()->ordered()->get();

        $listHtml = view('admin.drug-schedules._list', ['rows' => $rows])->render();

        $rowsForJs = $rows->map(fn (DrugSchedule $r) => [
            'id'           => $r->id,
            'code'         => $r->code,
            'name'         => $r->name,
            'description'  => $r->description,
            'country_code' => $r->country_code,
            'is_active'    => (bool) $r->is_active,
            'updated_at'   => $r->updated_at?->diffForHumans(),
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
