<?php

namespace App\Services\Hardware;

use App\Models\Company;
use App\Models\TradingDay;

/**
 * Renders a {@see TradingDay} into an ESC/POS byte stream for a thermal
 * printer — the same "Close Day" report {@see \App\Models\TradingDay} shows
 * on screen (shifts.day-report Blade view), just for the bill printer
 * instead of a browser print dialog. Mirrors {@see ZReportEscPosFormatter}'s
 * layout conventions (sections, rules, two-column rows) so a cashier who's
 * used to the shift Z-report recognises this immediately.
 *
 * The `$totals` array is the shared {@see \App\Actions\Shifts\ComputeTradingDayTotals}
 * output.
 */
class DayReportEscPosFormatter
{
    /**
     * @param array<string, mixed> $totals
     */
    public function format(TradingDay $day, array $totals, PrinterConfig $config, ?Company $company = null): string
    {
        $company ??= Company::current() ?? new Company();
        $w = $config->charWidth;
        $E = EscPosCommands::class;

        $out = $E::INIT;

        /* ── Store header (centered) ───────────────────────────── */
        $out .= $E::ALIGN_CENTER;
        $out .= $E::BOLD_ON.$E::SIZE_DOUBLE_HEIGHT;
        $out .= $this->clean($day->store?->name)."\n";
        $out .= $E::SIZE_NORMAL.$E::BOLD_OFF;
        if ($day->terminal) {
            $out .= $this->clean($day->terminal->name)."\n";
        }
        $out .= $E::BOLD_ON.$this->clean(__('shifts.sections.day_report'))."\n".$E::BOLD_OFF;

        /* ── Meta (left) ───────────────────────────────────────── */
        $out .= $E::ALIGN_LEFT;
        $out .= $this->rule($w);
        $out .= $this->row(__('shifts.day_fields.date'), $day->business_date->toDateString(), $w);
        $out .= $this->row(__('shifts.day_fields.opened'), format_datetime($day->opened_at).' — '.$this->clean($day->openedBy?->name), $w);
        if ($day->closed_at) {
            $out .= $this->row(__('shifts.day_fields.closed'), format_datetime($day->closed_at).' — '.$this->clean($day->closedBy?->name), $w);
        }
        $out .= $this->row(__('shifts.day_fields.shift_count'), (string) $totals['shift_count'], $w);

        /* ── Sales summary ─────────────────────────────────────── */
        $out .= $this->rule($w);
        $out .= $E::BOLD_ON.$this->clean(__('shifts.sections.sales_summary'))."\n".$E::BOLD_OFF;
        $out .= $this->row(__('shifts.totals.sales_total'), format_money($totals['sales_total']), $w);
        $out .= $this->row(__('shifts.totals.sales_count'), (string) $totals['sales_count'], $w);
        $out .= $this->row(__('shifts.totals.refunds_total'), '-'.format_money($totals['refunds_total']), $w);
        $out .= $this->row(__('shifts.day_fields.refunds_count'), (string) $totals['refunds_count'], $w);

        /* ── Payments received ─────────────────────────────────── */
        $out .= $this->rule($w);
        $out .= $E::BOLD_ON.$this->clean(__('shifts.sections.payments_received'))."\n".$E::BOLD_OFF;
        foreach (($totals['payment_totals'] ?? []) as $pt) {
            $out .= $this->row($this->clean($pt['name'] ?? ''), format_money($pt['amount'] ?? '0'), $w);
        }

        /* ── Cash drawer ───────────────────────────────────────── */
        $out .= $this->rule($w);
        $out .= $E::BOLD_ON.$this->clean(__('shifts.sections.cash_drawer'))."\n".$E::BOLD_OFF;
        $out .= $this->row(__('shifts.totals.opening_cash'), format_money($totals['opening_cash']), $w);
        $out .= $this->row(__('shifts.day_fields.closing_cash_total'), format_money($totals['closing_cash_total']), $w);
        $out .= $E::BOLD_ON;
        $out .= $this->row(__('shifts.totals.variance'), format_money($totals['cash_variance_total']), $w);
        $out .= $E::BOLD_OFF;

        /* ── Per-employee breakdown ─────────────────────────────── */
        $out .= $this->rule($w);
        $out .= $E::BOLD_ON.$this->clean(__('shifts.day_fields.by_employee'))."\n".$E::BOLD_OFF;
        foreach (($totals['employees'] ?? []) as $emp) {
            $out .= $this->row($this->clean($emp['cashier_name'] ?? ''), format_money($emp['sales_total'] ?? '0'), $w);
            if ((float) ($emp['refunds_total'] ?? 0) > 0) {
                $out .= $this->row('  '.__('shifts.totals.refunds_total'), '-'.format_money($emp['refunds_total']), $w);
            }
            if ((float) ($emp['cash_variance'] ?? 0) !== 0.0) {
                $out .= $this->row('  '.__('shifts.totals.variance'), format_money($emp['cash_variance']), $w);
            }
        }

        /* ── Feed + cut ────────────────────────────────────────── */
        $out .= $E::feed(5);
        if ($config->cutPaper) {
            $out .= $E::PARTIAL_CUT;
        }

        return $out;
    }

    /* ── Layout helpers (mirror ZReportEscPosFormatter) ─────────── */

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
