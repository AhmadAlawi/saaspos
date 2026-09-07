<?php

namespace App\Actions\Sales;

use App\Models\Store;
use App\Services\Tax\TaxResolver;
use App\Support\PricedCart;

/**
 * The single source of truth for what a cart costs.
 *
 * This is the per-line tax/total math lifted verbatim out of
 * {@see CompleteSale} so that EVERY place which needs an authoritative
 * total — the sale itself, and the QR/gateway charge amount — runs the
 * exact same code. Previously the cashier JS computed the grand total
 * with a simplified single-rate, float-accumulated estimate, while the
 * server computed it per-component with per-line 4dp rounding; the two
 * could disagree by a paisa or two, which in the QR flow meant the
 * gateway charged one number while the recorded sale held another.
 *
 * Pure pricing only — no stock locks, no DB writes, no side effects.
 * Callers resolve their own Product models (CompleteSale also locks
 * stock; the quote path doesn't need to).
 *
 * The math MUST stay byte-for-byte identical to what CompleteSale used
 * to do inline — see the matching scale-8-then-round-4-dp convention.
 */
class PriceCart
{
    public function __construct(
        private readonly TaxResolver $taxResolver,
    ) {}

    /**
     * @param array<int, array{raw: array<string, mixed>, product: \App\Models\Product, index: int}> $resolvedLines
     */
    public function handle(array $resolvedLines, Store $store): PricedCart
    {
        $subtotal      = '0';
        $discountTotal = '0';
        $taxTotal      = '0';
        $lines         = [];

        foreach ($resolvedLines as $resolved) {
            /** @var \App\Models\Product $product */
            $product = $resolved['product'];
            $raw     = $resolved['raw'];
            $i       = $resolved['index'];

            $qty     = (string) ($raw['quantity']         ?? '0');
            $unit    = (string) ($raw['unit_price']       ?? '0');
            $discPct = (string) ($raw['discount_percent'] ?? '0');
            $discAmt = (string) ($raw['discount_amount']  ?? '0');

            $gross = bcmul($qty, $unit, 8);

            // Discount: amount wins if both set, else percent of gross.
            if (bccomp($discAmt, '0', 8) > 0) {
                $discount = $discAmt;
            } elseif (bccomp($discPct, '0', 8) > 0) {
                $discount = bcdiv(bcmul($gross, $discPct, 8), '100', 8);
            } else {
                $discount = '0';
            }

            // Tax via the canonical resolver — inclusive/exclusive,
            // multi-component, and classification short-circuits all
            // live there. Pass discount so the taxable base is net.
            $breakdown = $this->taxResolver->resolveForLine(
                product:    $product,
                unitPrice:  $unit,
                quantity:   $qty,
                discount:   $this->round4($discount),
                store:      $store,
            );

            // Net (taxable) amount per line:
            //   - exclusive: net = gross − discount (tax adds on top)
            //   - inclusive: net = the back-extracted taxable amount the
            //     resolver already computed (using gross here would
            //     double-count the embedded tax).
            $netRounded = $breakdown->isInclusive
                ? $breakdown->taxableAmount()
                : $this->round4(bcsub($gross, $discount, 8));
            $taxRounded = $breakdown->total;
            $lineTotal  = bcadd($netRounded, $taxRounded, 4);

            $lines[] = [
                'index'      => $i,
                'product'    => $product,
                'raw'        => $raw,
                'net'        => $netRounded,
                'tax'        => $taxRounded,
                'line_total' => $lineTotal,
                'discount'   => $discount,
                'breakdown'  => $breakdown,
            ];

            $subtotal      = bcadd($subtotal,      $netRounded,              4);
            $discountTotal = bcadd($discountTotal, $this->round4($discount), 4);
            $taxTotal      = bcadd($taxTotal,      $taxRounded,              4);
        }

        $grandTotal = bcadd($subtotal, $taxTotal, 4);

        return new PricedCart(
            lines:         $lines,
            subtotal:      $subtotal,
            discountTotal: $discountTotal,
            taxTotal:      $taxTotal,
            grandTotal:    $grandTotal,
        );
    }

    /** Half-up round to 4dp via an 0.00005 nudge then scale-4 truncate. */
    private function round4(string $v): string
    {
        if ($v === '' || $v === '-' || $v === '.') {
            return '0.0000';
        }
        $sign   = bccomp($v, '0', 12) < 0 ? '-' : '';
        $abs    = ltrim($v, '-');
        $nudged = bcadd($abs, '0.00005', 8);
        return $sign.bcadd($nudged, '0', 4);
    }
}
