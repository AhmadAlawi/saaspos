<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reports\ExportAgedReceivables;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Reports\AgedReceivablesQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Aged Receivables — who owes how much and how overdue. Reads the
 * report from {@see AgedReceivablesQuery} and renders the screen + the
 * CSV/XLSX export. Gated by `reports.view_financial`.
 *
 * Snapshot semantics: rows reflect the live `balance_due` against
 * unvoided sales as of midnight on the chosen `as_of` date. The aging
 * formula counts days from `sale_date` to `as_of` (inclusive); future-
 * dated sales bucket as 0-30 with a 0-day age.
 */
class AgedReceivablesReportController extends Controller
{
    public function __construct(private AgedReceivablesQuery $query) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$asOf, $storeId] = $this->filters($request);
        $rows = ($this->query)($asOf, $storeId);

        return view('admin.reports.aged-receivables.index', [
            'rows'    => $rows,
            'summary' => $this->query->summarise($rows),
            'asOf'    => $asOf,
            'storeId' => $storeId,
            'stores'  => accessible_stores(),
        ]);
    }

    public function export(Request $request, ExportAgedReceivables $export): StreamedResponse
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$asOf, $storeId] = $this->filters($request);
        $rows   = ($this->query)($asOf, $storeId);
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';

        return $export($rows, $format);
    }

    /**
     * Parse + clamp the `as_of` and `store_id` request params.
     *
     * @return array{0: CarbonImmutable, 1: ?int}
     */
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
