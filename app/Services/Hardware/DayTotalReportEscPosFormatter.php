<?php

namespace App\Services\Hardware;

use App\Models\Company;
use App\Models\Store;

/**
 * Renders the compact "day total" rollup ({@see \App\Actions\Shifts\ComputeAllTerminalsDayTotals})
 * into an ESC/POS byte stream — deliberately just the 8 bottom-line
 * figures (sales, cards, cash, short, over, offer, refund, expenses),
 * not the per-terminal breakdown {@see AllTerminalsDayReportEscPosFormatter}
 * prints. Same layout conventions (row/rule helpers) as that formatter.
 */
class DayTotalReportEscPosFormatter
{
    /**
     * @param array<string, mixed> $data
     */
    public function format(Store $store, array $data, PrinterConfig $config, ?Company $company = null): string
    {
        $w = $config->charWidth;
        $E = EscPosCommands::class;
        $grand = $data['grand'];

        $out = $E::INIT;

        $out .= $E::ALIGN_CENTER;
        $out .= $E::BOLD_ON.$E::SIZE_DOUBLE_HEIGHT;
        $out .= $this->clean($store->name)."\n";
        $out .= $E::SIZE_NORMAL.$E::BOLD_OFF;
        $out .= $E::BOLD_ON.$this->clean(__('shifts.day_total.report_title'))."\n".$E::BOLD_OFF;

        $out .= $E::ALIGN_LEFT;
        $out .= $this->rule($w);
        $out .= $this->row(__('shifts.day_fields.date'), (string) $data['business_date'], $w);
        $out .= $this->row(__('shifts.day_fields.terminal_count'), (string) $grand['terminal_count'], $w);

        $out .= $this->rule($w);
        $out .= $this->row(__('shifts.day_total.total_sales'), format_money($grand['sales_total']), $w);
        $out .= $this->row(__('shifts.day_total.total_cards'), format_money($this->methodTotal($grand, 'card')), $w);
        $out .= $this->row(__('shifts.day_total.total_cash'), format_money($this->methodTotal($grand, 'cash')), $w);
        $out .= $this->row(__('shifts.day_total.total_short'), format_money($grand['variance_short_total']), $w);
        $out .= $this->row(__('shifts.day_total.total_over'), format_money($grand['variance_over_total']), $w);
        if ((float) $grand['discount_total'] > 0) {
            $out .= $this->row(__('shifts.day_total.total_offer'), format_money($grand['discount_total']), $w);
        }
        $out .= $this->row(__('shifts.day_total.total_refund'), format_money($grand['refunds_total']), $w);
        $out .= $this->row(__('shifts.day_total.total_expenses'), format_money($grand['pay_outs_total']), $w);

        $out .= $E::feed(5);
        if ($config->cutPaper) {
            $out .= $E::PARTIAL_CUT;
        }

        return $out;
    }

    /** Sum every payment_totals row of the given `type` (cash/card) — a
     *  store can have more than one method of the same type. */
    private function methodTotal(array $grand, string $type): string
    {
        $sum = '0';
        foreach (($grand['payment_totals'] ?? []) as $row) {
            if (($row['type'] ?? null) === $type) {
                $sum = bcadd($sum, (string) ($row['amount'] ?? '0'), 4);
            }
        }
        return $sum;
    }

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
