<?php

namespace App\Actions\Hardware;

use App\Models\Company;
use App\Models\Shift;
use App\Models\Terminal;
use App\Services\Hardware\PrinterConfig;
use Illuminate\Support\Facades\View;

/**
 * Print payload for the "open drawer, no sale" slip — same
 * {mode, paper, html, escpos_bytes} shape every other hardware payload
 * uses, resolved the SAME way the sale receipt is (PrinterConfig::fromTerminal
 * against the shift's own bound terminal), not the cashier page's
 * client-side `<meta name="pos-terminal-printer">` tag. That tag is
 * only ever populated at page LOAD from whatever terminal the browser
 * happened to be bound to at that moment — if the workstation's
 * terminal binding is stale, missing, or was never configured with
 * hardware settings, the client sees an empty config and silently
 * skips any kick attempt, even though the exact same shift's receipt
 * print (resolved server-side, fresh, every time) works fine.
 *
 * A WebUSB terminal is ALSO given a client-side kick attempt first
 * ({@see \App\Http\Controllers\Cashier\ShiftController::openDrawerWithPin()}'s
 * caller, `_autoOpenDrawerIfNeeded()`), but that attempt gates on the
 * same stale `<meta name="pos-terminal-printer">` tag this whole action
 * exists to route around — when it misses, `printReceipt()` still needs
 * real `escpos_bytes` to take the WebUSB branch instead of silently
 * falling back to a dialog-triggering browser-print. So this payload
 * carries a real drawer-kick command (mirrors `drawerKickBytes()` in
 * `resources/js/hardware/print-bridge.js`) whenever the SERVER-resolved
 * config says webusb — accurate every time, unlike the client meta tag.
 */
class PrepareCashDrawerSlipPayload
{
    /**
     * `$shift` is null for a shift-less drawer-open (no cashier clocked
     * in on this terminal yet) — falls back to `current_terminal()`/
     * `current_store()`, the same session-resolved context the shift
     * itself would otherwise have carried.
     *
     * @return array{mode:string, paper:string, html:string, escpos_bytes:string}
     */
    public function __invoke(?Shift $shift, string $performedBy, ?string $reason = null): array
    {
        $company  = Company::current() ?? new Company();
        $fallback = $company->receipt_paper_size ?: '80mm';

        $terminal = $shift?->terminal_id ? Terminal::find($shift->terminal_id) : current_terminal();
        $config   = PrinterConfig::fromTerminal($terminal, $fallback);

        $html = View::make('cashier.drawer-slip', [
            'store'       => $shift?->store ?? current_store(),
            'performedBy' => $performedBy,
            'reason'      => $reason,
            'paper'       => $config->paperWidth,
        ])->render();

        return [
            'mode'         => $config->mode,
            'paper'        => $config->paperWidth,
            'html'         => $html,
            'escpos_bytes' => $config->mode === 'webusb'
                ? base64_encode($this->drawerKickBytes($config->drawerPin))
                : '',
        ];
    }

    /** ESC p m t1 t2 — same generate-pulse command the JS driver sends. */
    private function drawerKickBytes(int $pin): string
    {
        return pack('C*', 0x1b, 0x70, $pin === 5 ? 0x01 : 0x00, 0x32, 0xfa);
    }
}
