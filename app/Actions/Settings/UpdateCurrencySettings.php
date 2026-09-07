<?php

namespace App\Actions\Settings;

use Illuminate\Support\Facades\DB;

/**
 * Persist the base-currency selection + its display format.
 *
 * Single source of truth: the format (symbol / placement / decimals /
 * separators) lives on the chosen `currencies` row, and
 * `company.base_currency_code` points at it. The whole app reads this
 * through `app_currency()`, so updating here propagates everywhere.
 *
 * Hook points:
 *   - action `currency.settings.before_update` → ($code, $format)
 *   - action `currency.settings.updated`       → ($code, $format)
 */
class UpdateCurrencySettings
{
    /** @param array<string, mixed> $format */
    public function __invoke(string $currencyCode, array $format): void
    {
        $currencyCode = strtoupper($currencyCode);

        do_action('currency.settings.before_update', $currencyCode, $format);

        DB::transaction(function () use ($currencyCode, $format) {
            DB::table('currencies')->where('code', $currencyCode)->update([
                'symbol'              => $format['symbol'],
                'symbol_first'        => $format['symbol_first'],
                'decimals'            => $format['decimals'],
                'thousands_separator' => $format['thousands_separator'],
                'decimal_separator'   => $format['decimal_separator'],
            ]);

            // Single-company install — one row. Point it at the chosen currency.
            DB::table('company')->update(['base_currency_code' => $currencyCode]);
        });

        // Drop the request-cached currency so subsequent reads (and the
        // redirect target) see the new values immediately.
        forget_app_currency();

        do_action('currency.settings.updated', $currencyCode, $format);
    }
}
