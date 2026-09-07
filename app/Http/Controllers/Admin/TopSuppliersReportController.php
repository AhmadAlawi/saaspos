<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reports\ExportTopSuppliers;
use App\Http\Controllers\Admin\Concerns\ResolvesReportFilters;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Reports\TopSuppliersQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TopSuppliersReportController extends Controller
{
    use ResolvesReportFilters;

    public function __construct(private TopSuppliersQuery $query) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('reports.view_suppliers'), 403);

        [$from, $to, $storeId, $period] = $this->reportFilters($request);
        $rows = ($this->query)($from, $to, $storeId);

        return view('admin.reports.top-suppliers.index', [
            'rows'    => $rows,
            'summary' => $this->query->summarise($rows),
            'from'    => $from,
            'to'      => $to,
            'storeId' => $storeId,
            'period'  => $period,
            'stores'  => Store::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, ExportTopSuppliers $export): StreamedResponse
    {
        abort_unless($request->user()?->hasPermission('reports.view_suppliers'), 403);

        [$from, $to, $storeId] = $this->reportFilters($request);
        $rows   = ($this->query)($from, $to, $storeId);
        $format = $this->reportFormat($request);

        return $export($rows, $from->toDateString(), $to->toDateString(), $format);
    }
}
