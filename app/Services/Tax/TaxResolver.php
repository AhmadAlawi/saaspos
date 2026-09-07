<?php

namespace App\Services\Tax;

use App\Models\Product;
use App\Models\Store;
use App\Models\TaxGroup;

/**
 * Single canonical tax-computation entry point. Sales, Purchases, and
 * any future flow that needs per-line tax math go through here so the
 * inclusive/exclusive rules + per-component split + rounding behave
 * identically everywhere.
 *
 * What Slice 1 handles (per `docs/features/taxation.md`):
 *   - Inclusive vs exclusive pricing (§8)
 *   - Multi-component groups (CGST+SGST, VAT+Surcharge, etc.)
 *   - Per-component HALF_UP rounding at 4dp (§16.1; the conservative
 *     default — India GST filing requires per-component)
 *   - Exempt / zero-rated / nil-rated classification short-circuits (§5.2)
 *
 * What Slice 1 deliberately does NOT handle (later slices):
 *   - Exemptions (product/customer-level — §9)         → Slice 2
 *   - Reverse charge (§10)                              → Slice 3
 *   - Composition scheme (§6.4)                         → Slice 3
 *   - Logical groups + jurisdiction switching (§7)      → Slice 4
 *
 * Each deferred concern has a documented hook in this method's flow so
 * adding it later is a single in-place edit, not a refactor.
 *
 * Hooks fired (per §22):
 *   action `tax.before_resolve` ($context)
 *   filter `tax.breakdown`      ($breakdown, $context)
 */
class TaxResolver
{
    /**
     * Compute the tax for a single sale/purchase line.
     *
     * Amounts are decimal strings at 4dp (system-wide convention).
     *
     * @param string $unitPrice   per-unit price as decimal string
     * @param string $quantity    quantity as decimal string
     * @param string $discount    pre-tax line-level discount amount (subtracted from unit*qty)
     * @param ?Store $store       store the sale happens in (drives inclusive default)
     * @param bool   $forceInclusive  override the store/group default
     */
    public function resolveForLine(
        Product $product,
        string  $unitPrice,
        string  $quantity,
        string  $discount = '0',
        ?Store  $store = null,
        bool    $forceInclusive = false,
    ): TaxBreakdown {
        $context = [
            'product_id' => $product->id,
            'store_id'   => $store?->id,
            'unit_price' => $unitPrice,
            'quantity'   => $quantity,
            'discount'   => $discount,
        ];
        do_action('tax.before_resolve', $context);

        // Future Slice 2/3 short-circuits (exemption, composition) land
        // here — return a zero breakdown with the right classification.

        $group = $product->taxGroup;
        if ($group === null || ! $group->is_active) {
            // No group on the product → no tax. Same shape as "exempt"
            // for the cart, but classification 'taxable' so reports
            // count the line in the "taxable / not assigned" bucket.
            return apply_filters('tax.breakdown', TaxBreakdown::none(), $context);
        }

        // Exempt / zero-rated / nil-rated → zero math, classification
        // preserved so filing reports can bucket the line correctly.
        if (in_array($group->classification, ['exempt', 'zero_rated', 'nil_rated'], true)
            || $group->is_reverse_charge) {
            return apply_filters(
                'tax.breakdown',
                TaxBreakdown::none($group->is_reverse_charge ? 'reverse_charge' : $group->classification),
                $context,
            );
        }

        // Pre-tax line subtotal: (qty × price) − discount. Scale 8 so
        // the inclusive split below doesn't lose precision before we
        // round each component to 4dp.
        $lineSubtotal = bcsub(bcmul($quantity, $unitPrice, 8), $discount, 8);
        if (bccomp($lineSubtotal, '0', 8) <= 0) {
            return apply_filters('tax.breakdown', TaxBreakdown::none($group->classification), $context);
        }

        $isInclusive = $forceInclusive || $this->resolveInclusive($group, $store);

        $components = $group->components;          // ordered by sort_order via the relation
        $rateTotal  = '0';
        foreach ($components as $c) {
            $rateTotal = bcadd($rateTotal, $this->dec($c->rate), 8);
        }

        // Edge case: a group with no components or 0% total. Treat as a
        // zero-tax line in the group's own classification.
        if (bccomp($rateTotal, '0', 8) === 0) {
            return apply_filters('tax.breakdown', TaxBreakdown::none($group->classification), $context);
        }

        // Two math paths:
        //   - exclusive: line_subtotal IS the taxable amount; tax added on top
        //   - inclusive: line_subtotal IS the gross; back-extract the taxable
        //                via taxable = gross / (1 + rate/100)
        if ($isInclusive) {
            $divisor      = bcadd('1', bcdiv($rateTotal, '100', 12), 12);
            $taxableAmount = bcdiv($lineSubtotal, $divisor, 8);
        } else {
            $taxableAmount = $lineSubtotal;
        }

        $componentsOut = [];
        $totalTax      = '0';
        foreach ($components as $c) {
            $rate = $this->dec($c->rate);
            $raw  = bcdiv(bcmul($taxableAmount, $rate, 12), '100', 12);
            $tax  = $this->round4($raw);

            $componentsOut[] = [
                'code'           => $c->code,
                'name'           => $c->name,
                'rate'           => $this->round4($rate),
                'taxable_amount' => $this->round4($taxableAmount),
                'tax_amount'     => $tax,
                'account_id'     => $c->accounting_account_id,
            ];

            $totalTax = bcadd($totalTax, $tax, 4);
        }

        $breakdown = new TaxBreakdown(
            total:          $totalTax,
            rateTotal:      $this->round4($rateTotal),
            isInclusive:    $isInclusive,
            classification: $group->classification,
            components:     $componentsOut,
        );

        return apply_filters('tax.breakdown', $breakdown, $context);
    }

    /**
     * Inclusive default resolution: group override → store default → false.
     * The group's `is_inclusive` only acts as an override when it differs
     * from the store default — `false` on the group means "use the store",
     * not "force exclusive". A future explicit `null` tri-state on the
     * column can sharpen this; for now we treat group `true` as "force
     * inclusive" and group `false` as "defer to store".
     */
    private function resolveInclusive(TaxGroup $group, ?Store $store): bool
    {
        if ($group->is_inclusive) {
            return true;
        }
        return (bool) ($store?->tax_inclusive_pricing ?? false);
    }

    private function dec(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '0';
        }
        return (string) $v;
    }

    /**
     * HALF_UP at 4dp away from zero. Matches `ComputePurchaseTotals` so
     * Sales + Purchases agree to the cent on the same input.
     */
    private function round4(string $v): string
    {
        $sign = bccomp($v, '0', 12) < 0 ? '-' : '';
        $abs  = ltrim($v, '-');
        $nudged = bcadd($abs, '0.00005', 8);
        return $sign.bcadd($nudged, '0', 4);
    }
}
