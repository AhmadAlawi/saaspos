<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reports\ExportTrialBalance;
use App\Http\Controllers\Controller;
use App\Services\Reports\TrialBalanceQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Trial Balance — every account's balance as of a date, debits and credits in
 * separate columns, which must total equal. Reads {@see TrialBalanceQuery} and
 * renders the screen + CSV/XLSX/PDF export. Gated by `reports.view_financial`.
 */
class TrialBalanceReportController extends Controller
{
    public function __construct(private TrialBalanceQuery $query) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$asOf, $storeId] = $this->filters($request);
        $rows = ($this->query)($asOf, $storeId);

        return view('admin.reports.trial-balance.index', [
            'rows'    => $rows,
            'summary' => $this->query->summarise($rows),
            'asOf'    => $asOf,
            'storeId' => $storeId,
            'stores'  => accessible_stores(),
        ]);
    }

    public function export(Request $request, ExportTrialBalance $export): StreamedResponse
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$asOf, $storeId] = $this->filters($request);
        $rows   = ($this->query)($asOf, $storeId);
        $format = in_array($request->query('format'), ['csv', 'xlsx', 'pdf'], true) ? $request->query('format') : 'csv';

        return $export($rows, $asOf->toDateString(), $format);
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
        $storeId = enforce_store_access($request->integer('store_id') ?: null);

        return [$asOf->startOfDay(), $storeId];
    }
}
