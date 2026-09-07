<?php

namespace App\Support;

use App\Models\Sale;

/**
 * Aggregates a completed {@see Sale} into an HSN-wise tax summary — the
 * block a GST tax invoice needs below the line items: one row per HSN
 * code carrying taxable value, total tax, and a per-component rollup
 * (CGST / SGST / IGST / …), so the customer's accountant can reconcile
 * tax by HSN.
 *
 * Reads the historical snapshot on each line (`hsn_snapshot`, the
 * `tax_breakdown` JSON) — never recomputes tax — so a reprinted receipt
 * matches the original even if the product's HSN or tax group later
 * changes. Lines without an HSN code are skipped (nothing to summarise).
 *
 * Shared by the HTML receipt (`sales.receipt`) and the ESC/POS thermal
 * formatter so both surfaces print an identical summary.
 */
final class ReceiptHsnSummary
{
    /**
     * @return list<array{
     *     hsn: string,
     *     taxable: string,
     *     tax: string,
     *     total: string,
     *     components: list<array{name: string, rate: string, amount: string}>
     * }>
     */
    public static function build(Sale $sale): array
    {
        $rows = [];

        foreach ($sale->items as $item) {
            $hsn = trim((string) ($item->hsn_snapshot ?? $item->product?->hsn_code ?? ''));
            if ($hsn === '') {
                continue;
            }

            $rows[$hsn] ??= ['hsn' => $hsn, 'taxable' => '0', 'tax' => '0', 'components' => []];
            $rows[$hsn]['taxable'] = bcadd($rows[$hsn]['taxable'], (string) $item->line_subtotal, 4);
            $rows[$hsn]['tax']     = bcadd($rows[$hsn]['tax'], (string) $item->tax_amount, 4);

            $breakdown = is_array($item->tax_breakdown) ? $item->tax_breakdown : [];
            foreach (($breakdown['components'] ?? []) as $c) {
                $code = (string) ($c['code'] ?? $c['name'] ?? '');
                if ($code === '') {
                    continue;
                }
                $rows[$hsn]['components'][$code] ??= [
                    'name'   => (string) ($c['name'] ?? $code),
                    'rate'   => (string) ($c['rate'] ?? '0'),
                    'amount' => '0',
                ];
                $rows[$hsn]['components'][$code]['amount'] = bcadd(
                    $rows[$hsn]['components'][$code]['amount'],
                    (string) ($c['tax_amount'] ?? '0'),
                    4,
                );
            }
        }

        return array_values(array_map(static function (array $row): array {
            $row['total']      = bcadd($row['taxable'], $row['tax'], 4);
            $row['components'] = array_values($row['components']);
            return $row;
        }, $rows));
    }
}
