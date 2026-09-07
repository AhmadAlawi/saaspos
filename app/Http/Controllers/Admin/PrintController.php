<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Hardware\PreparePrintPayload;
use App\Actions\Hardware\RecordPrintLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hardware\RecordPrintLogRequest;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Print pipeline endpoints (docs/features/hardware.md §15.1). The client
 * `<x-print-button>` fetches a sale's dual-format payload here, hands it
 * to the print bridge, then reports the outcome back to the log endpoint.
 */
class PrintController extends Controller
{
    /**
     * Dual-format print payload for a sale receipt: HTML for browser
     * print, ESC/POS bytes for WebUSB. Resolved against the current
     * terminal's printer config.
     */
    public function salePayload(Sale $sale, PreparePrintPayload $prepare): JsonResponse
    {
        $this->authorize('view', $sale);

        // Defense-in-depth: nothing upstream (service worker, browser HTTP
        // cache, a proxy) should ever serve a stale print payload — the
        // receipt content/bytes must reflect whatever's true right now,
        // not what this same URL returned on a previous request.
        return response()->json(($prepare)($sale, current_terminal()))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Dual-format print payload for a REFUND receipt — only the
     * refunded lines, never the rest of the original sale. Gated on
     * `sales.create` (any real cashier) rather than a refund-specific
     * permission: printing the receipt for a refund you (or a manager
     * on your behalf) just processed isn't itself a sensitive action —
     * the sensitive part already happened at the approval step.
     */
    public function refundPayload(Request $request, SaleReturn $saleReturn, PreparePrintPayload $prepare): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('sales.create'), 403);

        return response()->json(($prepare)->forReturn($saleReturn, current_terminal()))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Dual-format print payload for the customer's "corrected" copy of
     * the original sale after a refund — same sale number, refunded
     * lines dropped/reduced. Same permission shape as `refundPayload()`.
     */
    public function correctedCopyPayload(Request $request, Sale $sale, PreparePrintPayload $prepare): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('sales.create'), 403);

        return response()->json(($prepare)->forCorrectedCopy($sale, current_terminal()))
            ->header('Cache-Control', 'no-store');
    }

    /** Record a print attempt (success / failed / queued). */
    public function log(RecordPrintLogRequest $request, RecordPrintLog $record): JsonResponse
    {
        $log = ($record)($request->validated());

        return response()->json(['ok' => true, 'id' => $log->id]);
    }
}
