<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reports\ExportProfitAndLoss;
use App\Http\Controllers\Admin\Concerns\ResolvesReportFilters;
use App\Http\Controllers\Controller;
use App\Services\Reports\ProfitAndLossQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Profit & Loss (Income Statement) for a period. Reads
 * {@see ProfitAndLossQuery} and renders the statement + CSV/XLSX/PDF export.
 * Gated by `reports.view_financial`.
 */
class ProfitAndLossReportController extends Controller
{
    use ResolvesReportFilters;

    public function __construct(private ProfitAndLossQuery $query) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$from, $to, $storeId, $period] = $this->reportFilters($request);

        return view('admin.reports.profit-and-loss.index', [
            'data'    => ($this->query)($from, $to, $storeId),
            'from'    => $from,
            'to'      => $to,
            'storeId' => $storeId,
            'period'  => $period,
            'stores'  => accessible_stores(),
        ]);
    }

    public function export(Request $request, ExportProfitAndLoss $export): StreamedResponse
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$from, $to, $storeId] = $this->reportFilters($request);

        return $export(
            ($this->query)($from, $to, $storeId),
            $from->toDateString(),
            $to->toDateString(),
            $this->reportFormat($request),
        );
    }
}
