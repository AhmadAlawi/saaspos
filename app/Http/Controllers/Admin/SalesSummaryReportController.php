<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reports\ExportSalesSummary;
use App\Http\Controllers\Admin\Concerns\ResolvesReportFilters;
use App\Http\Controllers\Controller;
use App\Services\Reports\SalesSummaryQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesSummaryReportController extends Controller
{
    use ResolvesReportFilters;

    public function __construct(private SalesSummaryQuery $query) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$from, $to, $storeId, $period] = $this->reportFilters($request);
        $data = ($this->query)($from, $to, $storeId);

        return view('admin.reports.sales.index', [
            'data'    => $data,
            'from'    => $from,
            'to'      => $to,
            'storeId' => $storeId,
            'period'  => $period,
            'stores'  => accessible_stores(),
        ]);
    }

    public function export(Request $request, ExportSalesSummary $export): StreamedResponse
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$from, $to, $storeId] = $this->reportFilters($request);
        $data   = ($this->query)($from, $to, $storeId);
        $format = $this->reportFormat($request);

        return $export($data, $from->toDateString(), $to->toDateString(), $format);
    }
}
