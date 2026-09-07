<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reports\ExportSalesByProduct;
use App\Http\Controllers\Admin\Concerns\ResolvesReportFilters;
use App\Http\Controllers\Controller;
use App\Services\Reports\SalesByProductQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesByProductReportController extends Controller
{
    use ResolvesReportFilters;

    public function __construct(private SalesByProductQuery $query) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$from, $to, $storeId, $period] = $this->reportFilters($request);
        $rows    = ($this->query)($from, $to, $storeId);
        $summary = $this->query->summarise($rows);

        return view('admin.reports.sales-by-product.index', [
            'rows'    => $rows,
            'summary' => $summary,
            'from'    => $from,
            'to'      => $to,
            'storeId' => $storeId,
            'period'  => $period,
            'stores'  => accessible_stores(),
        ]);
    }

    public function export(Request $request, ExportSalesByProduct $export): StreamedResponse
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$from, $to, $storeId] = $this->reportFilters($request);
        $rows   = ($this->query)($from, $to, $storeId);
        $format = $this->reportFormat($request);

        return $export($rows, $from->toDateString(), $to->toDateString(), $format);
    }
}
