<?php

namespace App\Actions\Hardware;

use App\Actions\Shifts\ComputeShiftTotals;
use App\Models\Company;
use App\Models\Shift;
use App\Models\Terminal;
use App\Services\Hardware\PrinterConfig;
use App\Services\Hardware\ZReportEscPosFormatter;
use Illuminate\Support\Facades\View;

/**
 * Dual-format print payload for a shift Z-report (mirrors
 * {@see PreparePrintPayload} for sales): HTML for the browser-print path,
 * an ESC/POS byte stream (base64) for WebUSB. The client print bridge
 * picks the format the terminal supports.
 *
 * Extension points:
 *   - filter `z_report.print.html`  → ($html, $shift)
 *   - filter `z_report.print.bytes` → ($bytes, $shift, $config)
 */
class PrepareZReportPayload
{
    public function __construct(
        private ZReportEscPosFormatter $formatter,
        private ComputeShiftTotals $compute,
    ) {}

    /**
     * @return array{mode:string, paper:string, html:string, escpos_bytes:string}
     */
    public function __invoke(Shift $shift, ?Terminal $terminal = null): array
    {
        $shift->loadMissing([
            'store:id,name,code,address_line1,address_line2,city,state,postal_code,phone',
            'cashier:id,name',
        ]);

        $company  = Company::current() ?? new Company();
        $fallback = $company->receipt_paper_size ?: '80mm';
        $config   = PrinterConfig::fromTerminal($terminal, $fallback);
        $totals   = ($this->compute)($shift);

        // Computed here (not in an inline @php block in the view) so it's
        // always a real, unambiguous view variable — the store's address
        // fields are frequently all-null for a freshly provisioned store,
        // which was tripping an "Undefined variable $addrLines" error when
        // this lived inline in shifts/z-report-thermal.blade.php.
        $addrLines = array_filter([
            trim((string) ($shift->store->address_line1 ?? '')),
            trim((string) ($shift->store->address_line2 ?? '')),
            trim(implode(', ', array_filter([
                $shift->store->city ?? null,
                $shift->store->state ?? null,
                $shift->store->postal_code ?? null,
            ]))),
        ]);

        $html = View::make('shifts.z-report-thermal', [
            'shift'     => $shift,
            'totals'    => $totals,
            'company'   => $company,
            'paper'     => $config->paperWidth,
            'addrLines' => $addrLines,
        ])->render();
        $html = apply_filters('z_report.print.html', $html, $shift);

        $bytes = $this->formatter->format($shift, $totals, $config, $company);
        $bytes = apply_filters('z_report.print.bytes', $bytes, $shift, $config);

        return [
            'mode'         => $config->mode,
            'paper'        => $config->paperWidth,
            'html'         => $html,
            'escpos_bytes' => base64_encode($bytes),
        ];
    }
}
