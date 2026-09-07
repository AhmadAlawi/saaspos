<?php

namespace App\Actions\Hardware;

use App\Actions\Shifts\ComputeTradingDayTotals;
use App\Models\Company;
use App\Models\TradingDay;
use App\Services\Hardware\DayReportEscPosFormatter;
use App\Services\Hardware\PrinterConfig;
use Illuminate\Support\Facades\View;

/**
 * Print payload for the end-of-day "Close Day" report — same
 * {mode, paper, html, escpos_bytes} shape as {@see PrepareZReportPayload}
 * (the per-shift Z-report) and {@see PreparePrintPayload::forReturn()}
 * (refund receipts), but rolling up every shift under one
 * {@see TradingDay} instead of a single shift.
 *
 * Extension point:
 *   - filter `trading_day.print.bytes` → ($bytes, $day, $config)
 */
class PrepareDayReportPayload
{
    public function __construct(
        private readonly ComputeTradingDayTotals $compute,
        private readonly DayReportEscPosFormatter $formatter,
    ) {}

    /** @return array{mode:string, paper:string, html:string, escpos_bytes:string} */
    public function __invoke(TradingDay $day): array
    {
        $day->loadMissing([
            'store:id,name,code,address_line1,address_line2,city,state,postal_code,phone',
            'terminal:id,name,code',
            'openedBy:id,name',
            'closedBy:id,name',
        ]);

        $company  = Company::current() ?? new Company();
        $fallback = $company->receipt_paper_size ?: '80mm';
        // The trading day is itself scoped to (store, terminal), so its
        // own terminal drives the printer mode — same as every other
        // hardware payload, and a fix in its own right: this used to
        // hardcode `null` here, so a WebUSB-configured terminal's day
        // report always fell back to browser-print regardless.
        $config   = PrinterConfig::fromTerminal($day->terminal, $fallback);
        $totals   = ($this->compute)($day);

        $html = View::make('shifts.day-report', [
            'day'     => $day,
            'totals'  => $totals,
            'company' => $company,
            'paper'   => $config->paperWidth,
        ])->render();
        $html = apply_filters('trading_day.print.html', $html, $day);

        $bytes = $this->formatter->format($day, $totals, $config, $company);
        $bytes = apply_filters('trading_day.print.bytes', $bytes, $day, $config);

        return [
            'mode'         => $config->mode,
            'paper'        => $config->paperWidth,
            'html'         => $html,
            'escpos_bytes' => base64_encode($bytes),
        ];
    }
}
