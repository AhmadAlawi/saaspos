<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reports\ExportGeneralLedger;
use App\Http\Controllers\Admin\Concerns\ResolvesReportFilters;
use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * General Ledger — one account's journal lines over a date range with a
 * running balance. Reads {@see \App\Services\Reports\GeneralLedgerQuery} and
 * renders the screen + CSV/XLSX/PDF export. Gated by `reports.view_financial`.
 */
class GeneralLedgerReportController extends Controller
{
    use ResolvesReportFilters;

    public function __construct(private \App\Services\Reports\GeneralLedgerQuery $query) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$from, $to, $storeId] = $this->reportFilters($request);
        $accountId = $request->integer('account_id') ?: null;

        $result = $accountId
            ? ($this->query)($accountId, $from, $to, $storeId)
            : ['account' => null, 'opening' => '0', 'closing' => '0', 'rows' => []];

        return view('admin.reports.general-ledger.index', [
            'result'    => $result,
            'accountId' => $accountId,
            'from'      => $from,
            'to'        => $to,
            'storeId'   => $storeId,
            'accounts'  => Account::active()->orderBy('code')->get(['id', 'code', 'name']),
            'stores'    => accessible_stores(),
        ]);
    }

    public function export(Request $request, ExportGeneralLedger $export): StreamedResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        [$from, $to, $storeId] = $this->reportFilters($request);
        $accountId = $request->integer('account_id') ?: null;
        if (! $accountId) {
            return redirect()->route('admin.reports.general-ledger.index');
        }

        $result = ($this->query)($accountId, $from, $to, $storeId);

        return $export($result, $from->toDateString(), $to->toDateString(), $this->reportFormat($request));
    }
}
