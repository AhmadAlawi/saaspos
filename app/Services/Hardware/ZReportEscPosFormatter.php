<?php

namespace App\Services\Hardware;

use App\Models\Company;
use App\Models\Shift;

/**
 * Renders a closed {@see Shift} into an ESC/POS byte stream for a thermal
 * printer — the end-of-shift Z-report (docs/features/cash-drawer-shifts.md
 * §7). Mirrors the on-screen X/Z totals so the printed slip matches the
 * admin view: sales summary, payments received, and the cash-drawer
 * reconciliation with counted cash + variance.
 *
 * The `$totals` array is the shared {@see \App\Actions\Shifts\ComputeShiftTotals}
 * output; the counted-cash + variance come off the frozen shift row.
 */
class ZReportEscPosFormatter
{
    /**
     * @param array<string, mixed> $totals
     */
    public function format(Shift $shift, array $totals, PrinterConfig $config, ?Company $company = null): string
    {
        $company ??= Company::current() ?? new Company();
        $w = $config->charWidth;
        $E = EscPosCommands::class;

        $out = $E::INIT;

        /* ── Store header (centered) ───────────────────────────── */
        $out .= $E::ALIGN_CENTER;
        $out .= $E::BOLD_ON.$E::SIZE_DOUBLE_HEIGHT;
        $out .= $this->clean($shift->store?->name)."\n";
        $out .= $E::SIZE_NORMAL.$E::BOLD_OFF;
        $reportLabel = $shift->isClosed() ? __('shifts.sections.z_report') : __('shifts.sections.x_report');
        $out .= $E::BOLD_ON.$this->clean($reportLabel)."\n".$E::BOLD_OFF;

        /* ── Meta (left) ───────────────────────────────────────── */
        $out .= $E::ALIGN_LEFT;
        $out .= $this->rule($w);
        $out .= $this->row(__('shifts.fields.shift_id'), '#'.$shift->id, $w);
        $out .= $this->row(__('shifts.fields.cashier'), $this->clean($shift->cashier?->name), $w);
        $out .= $this->row(__('shifts.fields.opened'), format_datetime($shift->opened_at), $w);
        if ($shift->closed_at) {
            $out .= $this->row(__('shifts.fields.closed'), format_datetime($shift->closed_at), $w);
        }

        /* ── Sales summary ─────────────────────────────────────── */
        $out .= $this->rule($w);
        $out .= $E::BOLD_ON.$this->clean(__('shifts.sections.sales_summary'))."\n".$E::BOLD_OFF;
        $out .= $this->row(__('shifts.totals.sales_total'), format_money($totals['sales_total']), $w);
        $out .= $this->row(__('shifts.totals.discount_total'), format_money($totals['discount_total']), $w);
        $out .= $this->row(__('shifts.totals.tax_total'), format_money($totals['tax_total']), $w);
        $out .= $this->row(__('shifts.totals.refunds_total'), format_money($totals['refunds_total']), $w);
        $out .= $this->row(__('shifts.totals.sales_count'), (string) $totals['sales_count'], $w);

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
        $out .= $this->row(__('shifts.totals.cash_sales'), format_money($totals['cash_sales']), $w);
        $out .= $this->row(__('shifts.totals.cash_refunds'), '-'.format_money($totals['cash_refunds']), $w);
        $out .= $this->row(__('shifts.totals.pay_ins'), format_money($totals['pay_ins']), $w);
        $out .= $this->row(__('shifts.totals.pay_outs'), '-'.format_money($totals['pay_outs']), $w);
        if (! empty($totals['supplier_payouts'])) {
            $out .= '  '.$this->clean(__('shifts.totals.supplier_payments_heading'))."\n";
            foreach ($totals['supplier_payouts'] as $sp) {
                $out .= $this->row('    '.$this->clean($sp['supplier']), '-'.format_money($sp['amount']), $w);
            }
        }
        $out .= $E::BOLD_ON;
        $out .= $this->row(__('shifts.totals.expected_cash'), format_money($totals['expected_cash']), $w);
        $out .= $E::BOLD_OFF;

        if ($shift->closing_cash_counted !== null) {
            $out .= $this->row(__('shifts.totals.counted_cash'), format_money($shift->closing_cash_counted), $w);
            $out .= $E::BOLD_ON;
            $out .= $this->row(__('shifts.totals.variance'), format_money($shift->cash_variance), $w);
            $out .= $E::BOLD_OFF;
        }

        /* ── Feed + cut ────────────────────────────────────────── */
        $out .= $E::feed(5);
        if ($config->cutPaper) {
            $out .= $E::PARTIAL_CUT;
        }

        return $out;
    }

    /* ── Layout helpers (mirror EscPosFormatter) ────────────────── */

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
