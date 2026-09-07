<?php

namespace App\Support;

/**
 * Cash denomination lists for the drawer-counting helper
 * (docs/features/cash-drawer-shifts.md §6). Notes + coins per currency,
 * descending. A per-store custom list is a deferred setting (§11) — for
 * now we seed the common currencies and fall back to a generic set.
 */
class Denominations
{
    /**
     * Denomination face values for a currency, highest first.
     *
     * @return list<float>
     */
    public static function forCurrency(string $code): array
    {
        return self::SETS[strtoupper($code)] ?? self::SETS['_default'];
    }

    /** The list for the company's base currency (what the drawer holds). */
    public static function forActiveCurrency(): array
    {
        return self::forCurrency((string) (app_currency()['code'] ?? ''));
    }

    /** @var array<string, list<float>> */
    private const SETS = [
        'INR'      => [2000, 500, 200, 100, 50, 20, 10, 5, 2, 1],
        'USD'      => [100, 50, 20, 10, 5, 1, 0.25, 0.10, 0.05, 0.01],
        'GBP'      => [50, 20, 10, 5, 2, 1, 0.50, 0.20, 0.10, 0.05, 0.02, 0.01],
        'EUR'      => [500, 200, 100, 50, 20, 10, 5, 2, 1, 0.50, 0.20, 0.10, 0.05, 0.02, 0.01],
        'AED'      => [1000, 500, 200, 100, 50, 20, 10, 5, 1, 0.50, 0.25],
        'SAR'      => [500, 100, 50, 10, 5, 1, 0.50, 0.25],
        'NGN'      => [1000, 500, 200, 100, 50, 20, 10, 5],
        'ZAR'      => [200, 100, 50, 20, 10, 5, 2, 1, 0.50, 0.20, 0.10],
        'KES'      => [1000, 500, 200, 100, 50, 40, 20, 10, 5, 1],
        // Generic fallback — major notes + a couple of coins.
        '_default' => [500, 200, 100, 50, 20, 10, 5, 2, 1, 0.50, 0.10],
    ];
}
