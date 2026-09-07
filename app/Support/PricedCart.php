<?php

namespace App\Support;

/**
 * Immutable result of pricing a cart — the single, server-authoritative
 * answer to "what does this cart cost?". Produced by {@see \App\Actions\Sales\PriceCart}
 * and consumed by both {@see \App\Actions\Sales\CompleteSale} (to record
 * the sale) and the QR payment-session creation (to charge the customer),
 * so the amount charged can never disagree with the amount recorded.
 *
 * All money strings are at 4dp. `lines` carries the per-line breakdown
 * CompleteSale needs to build its `sale_items` rows.
 *
 * @phpstan-type PricedLine array{
 *     index: int,
 *     product: \App\Models\Product,
 *     raw: array<string, mixed>,
 *     net: string,
 *     tax: string,
 *     line_total: string,
 *     discount: string,
 *     breakdown: \App\Services\Tax\TaxBreakdown
 * }
 */
final class PricedCart
{
    /**
     * @param array<int, array<string, mixed>> $lines  per-line priced rows (see PricedLine)
     */
    public function __construct(
        public readonly array $lines,
        public readonly string $subtotal,
        public readonly string $discountTotal,
        public readonly string $taxTotal,
        public readonly string $grandTotal,
    ) {}
}
