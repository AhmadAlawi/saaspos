<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reports\ExportCashFlow;
use App\Http\Controllers\Admin\Concerns\ResolvesReportFilters;
use App\Http\Controllers\Controller;
use App\Services\Reports\CashFlowQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cash Flow (direct method) for a period. Reads {@see CashFlowQuery} and
 * renders the statement + CSV/XLSX/PDF export. Gated by `reports.view_financial`.
 */
class CashFlowReportController extends Controller
{
    use ResolvesReportFilters;

    public function __construct(private CashFlowQuery $query) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$from, $to, $storeId, $period] = $this->reportFilters($request);

        return view('admin.reports.cash-flow.index', [
            'data'    => ($this->query)($from, $to, $storeId),
            'from'    => $from,
            'to'      => $to,
            'storeId' => $storeId,
            'period'  => $period,
            'stores'  => accessible_stores(),
        ]);
    }

    public function export(Request $request, ExportCashFlow $export): StreamedResponse
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
