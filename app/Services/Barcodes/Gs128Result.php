<?php

namespace App\Services\Barcodes;

/**
 * Decoded contents of a GS1-128 barcode (docs/features/hardware.md §7.4).
 * Weighing-scale labels at a supermarket pack a GTIN plus a measured
 * weight and/or price into one barcode using GS1 Application Identifiers;
 * this is the structured result of pulling those apart.
 */
final class Gs128Result
{
    /**
     * @param string|null $gtin   AI 01 — the product's GTIN-14.
     * @param float|null  $weight AI 310x — net weight in kg.
     * @param float|null  $price  AI 392x / 393x — amount payable.
     * @param string|null $expiry AI 17 — expiry as Y-m-d.
     * @param string|null $batch  AI 10 — batch / lot number.
     * @param array<string, string> $ais All raw AI → value pairs decoded.
     */
    public function __construct(
        public readonly ?string $gtin = null,
        public readonly ?float $weight = null,
        public readonly ?float $price = null,
        public readonly ?string $expiry = null,
        public readonly ?string $batch = null,
        public readonly array $ais = [],
    ) {}

    /** True when at least one recognised AI was decoded. */
    public function isEmpty(): bool
    {
        return $this->ais === [];
    }

    /**
     * The GTIN with leading zeros trimmed to the 13/12-digit form most
     * product catalogues store (a GTIN-14 is a 13-digit EAN padded with a
     * leading packaging digit). Useful for matching against a stored
     * `barcode` column.
     */
    public function normalisedGtin(): ?string
    {
        if ($this->gtin === null) {
            return null;
        }

        return ltrim($this->gtin, '0') !== '' ? ltrim($this->gtin, '0') : '0';
    }
}
