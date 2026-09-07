<?php

namespace App\Http\Controllers\Admin;

use App\Actions\TaxClassifications\CreateTaxClassification;
use App\Actions\TaxClassifications\DeleteTaxClassification;
use App\Actions\TaxClassifications\ExportTaxClassifications;
use App\Actions\TaxClassifications\UpdateTaxClassification;
use App\Exceptions\TaxClassificationInUse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaxClassificationRequest;
use App\Models\TaxClassification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxClassificationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('settings.tax.view');

        $rows       = TaxClassification::ordered()->get();
        $selectedId = $request->integer('selected');
        $isNew      = $request->boolean('new');
        $selected   = $selectedId ? $rows->firstWhere('id', $selectedId) : null;

        return view('admin.settings.tax.classifications.index', [
            'rows'     => $rows,
            'selected' => $selected,
            'isNew'    => $isNew,
        ]);
    }

    public function store(TaxClassificationRequest $request, CreateTaxClassification $create): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.tax.update');

        $row     = $create($request->persistedAttributes());
        $message = __('tax.classifications.flash.created', ['name' => $row->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($row->id, $message);
        }

        return redirect()
            ->route('admin.settings.tax.classifications.index', ['selected' => $row->id])
            ->with('success', $message);
    }

    public function update(TaxClassificationRequest $request, TaxClassification $taxClassification, UpdateTaxClassification $update): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.tax.update');

        $update($taxClassification, $request->persistedAttributes());
        $message = __('tax.classifications.flash.updated', ['name' => $taxClassification->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($taxClassification->id, $message);
        }

        return redirect()
            ->route('admin.settings.tax.classifications.index', ['selected' => $taxClassification->id])
            ->with('success', $message);
    }

    public function destroy(Request $request, TaxClassification $taxClassification, DeleteTaxClassification $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.tax.update');

        $name = $taxClassification->name;

        try {
            $delete($taxClassification);
        } catch (TaxClassificationInUse $e) {
            $msg = __('tax.classifications.errors.in_use', ['count' => $e->count, 'name' => $name]);
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()
                ->route('admin.settings.tax.classifications.index', ['selected' => $taxClassification->id])
                ->with('error', $msg);
        }

        $message = __('tax.classifications.flash.deleted', ['name' => $name]);

        if ($request->wantsJson()) {
            return $this->freshListJson(null, $message);
        }

        return redirect()
            ->route('admin.settings.tax.classifications.index')
            ->with('success', $message);
    }

    public function deactivateCheck(TaxClassification $taxClassification): JsonResponse
    {
        $this->authorize('settings.tax.view');
        return response()->json([
            'count' => $taxClassification->taxGroups()->count(),
        ]);
    }

    public function export(Request $request, ExportTaxClassifications $export): \Symfony\Component\HttpFoundation\StreamedResponse
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
        $rows     = TaxClassification::ordered()->get();
        $listHtml = view('admin.settings.tax.classifications._list', ['rows' => $rows])->render();
        $rowsJs   = $rows->map(fn (TaxClassification $r) => [
            'id'          => $r->id,
            'name'        => $r->name,
            'slug'        => $r->slug,
            'description' => $r->description,
            'sort_order'  => $r->sort_order,
            'is_active'   => (bool) $r->is_active,
            'updated_at'  => $r->updated_at?->diffForHumans(),
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
