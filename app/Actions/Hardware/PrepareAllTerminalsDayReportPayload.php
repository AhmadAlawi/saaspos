<?php

namespace App\Actions\Hardware;

use App\Actions\Shifts\ComputeAllTerminalsDayTotals;
use App\Models\Company;
use App\Models\Store;
use App\Services\Hardware\AllTerminalsDayReportEscPosFormatter;
use App\Services\Hardware\PrinterConfig;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\View;

/**
 * Print payload for the "all terminals, one click" rollup — same
 * {mode, paper, html, escpos_bytes} shape as {@see PrepareDayReportPayload},
 * but combining every terminal's TradingDay for one store+date instead
 * of a single terminal's.
 *
 * Uses the CLICKING WORKSTATION's own bound terminal ({@see current_terminal()})
 * for printer config — same terminal every other print button on that
 * machine already uses — so this goes to the same printer as the
 * single-terminal day report, not a separate browser-print fallback.
 * Falls back to PrinterConfig::default() only when this workstation
 * isn't bound to any terminal yet.
 */
class PrepareAllTerminalsDayReportPayload
{
    public function __construct(
        private readonly ComputeAllTerminalsDayTotals $compute,
        private readonly AllTerminalsDayReportEscPosFormatter $formatter,
    ) {}

    /** @return array{mode:string, paper:string, html:string, escpos_bytes:string} */
    public function __invoke(Store $store, CarbonInterface $businessDate): array
    {
        $company = Company::current() ?? new Company();
        $fallback = $company->receipt_paper_size ?: '80mm';
        $config  = PrinterConfig::fromTerminal(current_terminal(), $fallback);
        $data    = ($this->compute)((int) $store->id, $businessDate);

        $html = View::make('shifts.all-terminals-report', [
            'store'   => $store,
            'data'    => $data,
            'company' => $company,
            'paper'   => $config->paperWidth,
        ])->render();
        $html = apply_filters('trading_day.all_terminals.print.html', $html, $store, $data);

        $bytes = $this->formatter->format($store, $data, $config, $company);
        $bytes = apply_filters('trading_day.all_terminals.print.bytes', $bytes, $store, $data, $config);

        return [
            'mode'         => $config->mode,
            'paper'        => $config->paperWidth,
            'html'         => $html,
            'escpos_bytes' => base64_encode($bytes),
        ];
    }
}
