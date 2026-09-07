<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Hardware\PrepareTestPrint;
use App\Http\Controllers\Controller;
use App\Models\PrintLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hardware diagnostics (docs/features/hardware.md §10). A status board
 * for the active terminal's peripherals plus non-destructive test
 * actions (test print, drawer kick) the operator runs to verify hardware
 * before a busy shift. The actual device exercise happens client-side
 * through the print bridge; this controller serves the board + the test
 * payload and surfaces recent print stats from `print_logs`.
 */
class HardwareController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('hardware.diagnostics'), 403);

        $terminal = current_terminal();
        $receipt  = data_get($terminal?->default_printer_config, 'receipt_printer', []);

        return view('admin.settings.hardware', [
            'terminal'    => $terminal,
            'receiptMode' => $receipt['mode'] ?? null,
            'paperWidth'  => $receipt['paper_width'] ?? null,
            'drawerMode'  => data_get($terminal?->default_printer_config, 'cash_drawer.mode'),
            'stats'       => $this->recentPrintStats(),
            'canTest'     => (bool) $request->user()?->hasPermission('hardware.test_print'),
        ]);
    }

    /** Dual-format test-print payload for the print bridge. */
    public function testPrint(Request $request, PrepareTestPrint $prepare): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('hardware.test_print'), 403);

        return response()->json(($prepare)(current_terminal()));
    }

    /**
     * Print counts for the active store over the last 7 days, for the
     * "recent prints" line on each board row.
     *
     * @return array{total:int, success:int, failed:int, last_at:?string}
     */
    private function recentPrintStats(): array
    {
        $base = PrintLog::query()
            ->where('store_id', current_store_id())
            ->where('created_at', '>=', now()->subDays(7));

        return [
            'total'   => (clone $base)->count(),
            'success' => (clone $base)->where('status', 'success')->count(),
            'failed'  => (clone $base)->whereIn('status', ['failed', 'queued'])->count(),
            'last_at' => (clone $base)->max('created_at') ?: null,
        ];
    }
}
