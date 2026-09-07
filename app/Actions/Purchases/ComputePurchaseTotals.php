<?php

namespace App\Actions\Purchases;

use App\Models\TaxGroup;

/**
 * Pure totals computation for a purchase form payload.
 *
 * Takes the raw line-items array from the Form Request and returns a
 * normalised `[$lines, $header]` tuple where:
 *   - each line has its `discount_amount`, `tax_amount`, `line_total`
 *     filled in based on `quantity`, `unit_cost`, `discount_percent`,
 *     and the picked tax group's total rate
 *   - `$header` carries `subtotal` (sum of pre-tax line totals),
 *     `discount_total`, `tax_total`, and `grand_total`
 *
 * This is a pure function — no DB writes, no side effects, no hooks.
 * The action layer calls it from inside the transaction, then persists.
 *
 * Multi-currency: amounts are in the purchase's own currency. The
 * exchange-rate-to-base on the header is just stored — no conversion
 * here; that's a Sales/Money slice concern.
 */
class ComputePurchaseTotals
{
    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, string>}
     */
    public function __invoke(array $lines): array
    {
        $taxRates = $this->taxRatesById($lines);

        $subtotal       = '0';
        $discountTotal  = '0';
        $taxTotal       = '0';

        $out = [];
        foreach (array_values($lines) as $i => $raw) {
            $qty             = $this->dec($raw['quantity']       ?? 0);
            $unit            = $this->dec($raw['unit_cost']      ?? 0);
            $discountPercent = $this->dec($raw['discount_percent'] ?? 0);
            $taxGroupId      = isset($raw['tax_group_id']) && $raw['tax_group_id'] !== '' ? (int) $raw['tax_group_id'] : null;
            $taxRate         = $taxRates[$taxGroupId] ?? '0';

            // Per-line math at scale 8, then HALF_UP-round each
            // component to 4dp. We accumulate the ROUNDED values into
            // the header totals so the invariant
            //   sum(items.line_total) == subtotal + tax_total == grand_total
            // holds exactly. (Earlier version summed the 8-scale
            // intermediates and ended up 0.0001–0.0003 off vs. summing
            // the persisted line_totals — a real footgun for the
            // future payment guard which compares against grand_total.)
            $gross    = bcmul($qty, $unit, 8);
            $discount = bcdiv(bcmul($gross, $discountPercent, 8), '100', 8);
            $net      = bcsub($gross, $discount, 8);
            $tax      = bcdiv(bcmul($net,   $taxRate,         8), '100', 8);

            $netR      = $this->round4($net);
            $taxR      = $this->round4($tax);
            $discountR = $this->round4($discount);
            $lineTotR  = bcadd($netR, $taxR, 4); // exact, both at 4dp

            $line = [
                'product_id'       => isset($raw['product_id']) ? (int) $raw['product_id'] : null,
                'variant_id'       => isset($raw['variant_id']) && $raw['variant_id'] !== '' ? (int) $raw['variant_id'] : null,
                'batch_number'     => $raw['batch_number']     ?? null,
                'manufacture_date' => $raw['manufacture_date'] ?? null,
                'expiry_date'      => $raw['expiry_date']      ?? null,
                'quantity'         => $this->round4($qty),
                'unit_cost'        => $this->round4($unit),
                'discount_percent' => $this->round4($discountPercent),
                'discount_amount'  => $discountR,
                'tax_group_id'     => $taxGroupId,
                'tax_amount'       => $taxR,
                'line_total'       => $lineTotR,
                'sort_order'       => $i,
            ];

            $out[] = $line;

            // Accumulate rounded values for invariant consistency.
            $subtotal      = bcadd($subtotal,      $netR,      4);
            $discountTotal = bcadd($discountTotal, $discountR, 4);
            $taxTotal      = bcadd($taxTotal,      $taxR,      4);
        }

        $grandTotal = bcadd($subtotal, $taxTotal, 4);

        return [
            $out,
            [
                'subtotal'       => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total'      => $taxTotal,
                'grand_total'    => $grandTotal,
            ],
        ];
    }

    /**
     * Resolve every tax_group_id mentioned in the lines to a total rate
     * (sum of component percents). Cached in one query.
     *
     * @param array<int, array<string, mixed>> $lines
     * @return array<int, string>
     */
    private function taxRatesById(array $lines): array
    {
        $ids = array_values(array_filter(array_unique(array_map(
            fn ($l) => isset($l['tax_group_id']) && $l['tax_group_id'] !== '' ? (int) $l['tax_group_id'] : null,
            $lines,
        ))));
        if (empty($ids)) {
            return [];
        }

        return TaxGroup::query()
            ->whereIn('id', $ids)
            ->with('components:id,rate')
            ->get(['id'])
            ->mapWithKeys(function ($g) {
                $sum = '0';
                foreach ($g->components as $c) {
                    $sum = bcadd($sum, $this->dec($c->rate), 8);
                }
                return [$g->id => $sum];
            })
            ->all();
    }

    private function dec(mixed $v): string
    {
        if ($v === null || $v === '') return '0';
        return (string) $v;
    }

    /**
     * Round-half-up AWAY FROM ZERO at 4 decimal places.
     *
     * Convention chosen deliberately:
     *   - Matches PHP_ROUND_HALF_UP, the financial-accounting standard.
     *   - `0.00005`  → `0.0001`
     *   - `-0.00005` → `-0.0001`
     *
     * NOT round-half-to-even ("banker's") and NOT round-half-toward-+inf
     * ("HALF_CEILING"). If future flows ever need a different rounding
     * mode (return-credit handling, tax-pull-back scenarios), accept it
     * as a parameter rather than overloading this single helper.
     *
     * Safe at this scale because money values are bounded by
     * DECIMAL(15,4) — bcmath never overflows for valid amounts.
     */
    private function round4(string $v): string
    {
        $sign = bccomp($v, '0', 12) < 0 ? '-' : '';
        $abs  = ltrim($v, '-');
        $nudged = bcadd($abs, '0.00005', 8);
        return $sign.bcadd($nudged, '0', 4);
    }
}
