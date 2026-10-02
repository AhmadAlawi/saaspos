<?php

namespace App\Services\Hardware;

use App\Models\Company;
use App\Models\Store;
use App\Models\TradingDay;

/**
 * Renders the "all terminals, one click" rollup ({@see \App\Actions\Shifts\ComputeAllTerminalsDayTotals})
 * into an ESC/POS byte stream — same layout conventions as
 * {@see DayReportEscPosFormatter}, just one section per terminal
 * followed by a grand total instead of one terminal's own sections.
 *
 * The `$data` array is the shared ComputeAllTerminalsDayTotals output.
 */
class AllTerminalsDayReportEscPosFormatter
{
    /**
     * @param array<string, mixed> $data
     */
    public function format(Store $store, array $data, PrinterConfig $config, ?Company $company = null): string
    {
        $w = $config->charWidth;
        $E = EscPosCommands::class;

        $out = $E::INIT;

        /* ── Store header (centered) ───────────────────────────── */
        $out .= $E::ALIGN_CENTER;
        $out .= $E::BOLD_ON.$E::SIZE_DOUBLE_HEIGHT;
        $out .= $this->clean($store->name)."\n";
        $out .= $E::SIZE_NORMAL.$E::BOLD_OFF;
        $out .= $E::BOLD_ON.$this->clean(__('shifts.sections.all_terminals_report'))."\n".$E::BOLD_OFF;

        /* ── Meta (left) ───────────────────────────────────────── */
        $out .= $E::ALIGN_LEFT;
        $out .= $this->rule($w);
        $out .= $this->row(__('shifts.day_fields.date'), (string) $data['business_date'], $w);
        $out .= $this->row(__('shifts.day_fields.terminal_count'), (string) $data['grand']['terminal_count'], $w);

        /* ── One section per terminal ──────────────────────────── */
        foreach ($data['terminals'] as $t) {
            $out .= $this->rule($w);
            $label = $t['terminal_name'];
            if ($t['status'] !== TradingDay::STATUS_CLOSED) {
                $label .= ' — '.__('shifts.day.status_open');
            }
            $out .= $E::BOLD_ON.$this->clean($label)."\n".$E::BOLD_OFF;

            $tt = $t['totals'];
            $out .= $this->row(__('shifts.totals.sales_total'), format_money($tt['sales_total']), $w);
            $out .= $this->row(__('shifts.totals.sales_count'), (string) $tt['sales_count'], $w);
            if ((float) $tt['refunds_total'] > 0) {
                $out .= $this->row(__('shifts.totals.refunds_total'), '-'.format_money($tt['refunds_total']), $w);
            }
            $out .= $this->row(__('shifts.totals.variance'), format_money($tt['cash_variance_total']), $w);

            // Only worth a sub-breakdown when more than one cashier
            // rotated through this till today — matches the on-screen
            // report's same threshold.
            if (count($tt['employees']) > 1) {
                foreach ($tt['employees'] as $emp) {
                    $out .= $this->row('  '.$this->clean($emp['cashier_name'] ?? ''), format_money($emp['sales_total'] ?? '0'), $w);
                }
            }
        }

        /* ── Grand total ───────────────────────────────────────── */
        $grand = $data['grand'];
        $out .= $this->rule($w);
        $out .= $E::BOLD_ON.$this->clean(__('shifts.day_fields.grand_total'))."\n".$E::BOLD_OFF;
        $out .= $this->row(__('shifts.totals.sales_total'), format_money($grand['sales_total']), $w);
        $out .= $this->row(__('shifts.totals.sales_count'), (string) $grand['sales_count'], $w);
        $out .= $this->row(__('shifts.totals.refunds_total'), '-'.format_money($grand['refunds_total']), $w);
        $out .= $this->row(__('shifts.day_fields.refunds_count'), (string) $grand['refunds_count'], $w);

        $out .= $this->rule($w);
        $out .= $E::BOLD_ON.$this->clean(__('shifts.sections.payments_received'))."\n".$E::BOLD_OFF;
        foreach (($grand['payment_totals'] ?? []) as $pt) {
            $out .= $this->row($this->clean($pt['name'] ?? ''), format_money($pt['amount'] ?? '0'), $w);
        }

        $out .= $this->rule($w);
        $out .= $E::BOLD_ON.$this->clean(__('shifts.sections.cash_drawer'))."\n".$E::BOLD_OFF;
        $out .= $this->row(__('shifts.totals.opening_cash'), format_money($grand['opening_cash']), $w);
        $out .= $this->row(__('shifts.day_fields.closing_cash_total'), format_money($grand['closing_cash_total']), $w);
        $out .= $E::BOLD_ON;
        $out .= $this->row(__('shifts.totals.variance'), format_money($grand['cash_variance_total']), $w);
        $out .= $E::BOLD_OFF;

        /* ── Feed + cut ────────────────────────────────────────── */
        $out .= $E::feed(5);
        if ($config->cutPaper) {
            $out .= $E::PARTIAL_CUT;
        }

        return $out;
    }

    /* ── Layout helpers (mirror DayReportEscPosFormatter) ────────── */

    private function rule(int $width): string
    {
        return str_repeat('-', max(1, $width))."\n";
    }

    private function row(string $left, string $right, int $width): string
    {
        $left  = trim($left);
        $right = trim($right);

        $maxLeft = max(0, $width - $this->displayWidth($right) - 1);
        if ($this->displayWidth($left) > $maxLeft) {
            $left = mb_strimwidth($left, 0, $maxLeft, '');
        }

        $gap = max(1, $width - $this->displayWidth($left) - $this->displayWidth($right));

        return $left.str_repeat(' ', $gap).$right."\n";
    }

    private function displayWidth(string $text): int
    {
        return function_exists('mb_strwidth') ? mb_strwidth($text) : strlen($text);
    }

    private function clean(?string $text): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $text) ?? '');
    }
}
