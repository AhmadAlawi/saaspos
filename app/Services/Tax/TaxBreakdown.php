<?php

namespace App\Services\Tax;

/**
 * Per-line tax computation result. Immutable; round-trips through JSON
 * verbatim so `sale_items.tax_breakdown` can store it and reports / returns
 * use it as-is without recomputing (feature doc §6.3 — historical lines
 * never re-resolve).
 *
 * Shape on the wire (one component, exclusive):
 *   {
 *     "total":          "9.0000",
 *     "rate_total":     "18.0000",
 *     "is_inclusive":   false,
 *     "classification": "taxable",
 *     "components": [
 *       { "code": "IN_CGST_9", "name": "CGST 9%", "rate": "9.0000",
 *         "taxable_amount": "50.0000", "tax_amount": "4.5000",
 *         "account_id": 2210 },
 *       { ... }
 *     ]
 *   }
 *
 * Amounts are decimal strings at 4dp — same convention as every other
 * money column in the system.
 *
 * @phpstan-type Component array{code:string,name:string,rate:string,taxable_amount:string,tax_amount:string,account_id:?int}
 */
final class TaxBreakdown implements \JsonSerializable
{
    /**
     * @param list<Component> $components
     */
    public function __construct(
        public readonly string $total,
        public readonly string $rateTotal,
        public readonly bool   $isInclusive,
        public readonly string $classification,
        public readonly array  $components,
    ) {}

    /** Zero-tax breakdown — short-circuit for groupless products, exempt
     *  classifications, or any future opt-out path. Keeps callers from
     *  having to special-case "no tax" anywhere in the math. */
    public static function none(string $classification = 'taxable'): self
    {
        return new self(
            total:          '0.0000',
            rateTotal:      '0.0000',
            isInclusive:    false,
            classification: $classification,
            components:     [],
        );
    }

    /** Sum of taxable amounts across components. Equal for every
     *  component within a single line — we expose the first one. */
    public function taxableAmount(): string
    {
        return $this->components[0]['taxable_amount'] ?? '0.0000';
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'total'          => $this->total,
            'rate_total'     => $this->rateTotal,
            'is_inclusive'   => $this->isInclusive,
            'classification' => $this->classification,
            'components'     => $this->components,
        ];
    }

    /** Reconstructs from a JSON-decoded array (for reports / returns
     *  that need to walk the snapshot stored on `sale_items`). */
    public static function fromArray(array $data): self
    {
        return new self(
            total:          (string) ($data['total']          ?? '0.0000'),
            rateTotal:      (string) ($data['rate_total']     ?? '0.0000'),
            isInclusive:    (bool)   ($data['is_inclusive']   ?? false),
            classification: (string) ($data['classification'] ?? 'taxable'),
            components:     array_values($data['components']  ?? []),
        );
    }
}
