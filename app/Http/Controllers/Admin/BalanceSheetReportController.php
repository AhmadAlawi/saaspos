<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reports\ExportBalanceSheet;
use App\Http\Controllers\Controller;
use App\Services\Reports\BalanceSheetQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Balance Sheet as of a date. Reads {@see BalanceSheetQuery} and renders the
 * statement + CSV/XLSX/PDF export. Gated by `reports.view_financial`.
 */
class BalanceSheetReportController extends Controller
{
    public function __construct(private BalanceSheetQuery $query) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$asOf, $storeId] = $this->filters($request);

        return view('admin.reports.balance-sheet.index', [
            'data'    => ($this->query)($asOf, $storeId),
            'asOf'    => $asOf,
            'storeId' => $storeId,
            'stores'  => accessible_stores(),
        ]);
    }

    public function export(Request $request, ExportBalanceSheet $export): StreamedResponse
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$asOf, $storeId] = $this->filters($request);
        $format = in_array($request->query('format'), ['csv', 'xlsx', 'pdf'], true) ? $request->query('format') : 'csv';

        return $export(($this->query)($asOf, $storeId), $asOf->toDateString(), $format);
    }

    /** @return array{0: CarbonImmutable, 1: ?int} */
    private function filters(Request $request): array
    {
        $asOf = $request->query('as_of');
        try {
            $asOf = $asOf ? CarbonImmutable::parse((string) $asOf) : CarbonImmutable::today();
        } catch (\Throwable) {
            $asOf = CarbonImmutable::today();
        }

        return [$asOf->startOfDay(), enforce_store_access($request->integer('store_id') ?: null)];
    }
}
