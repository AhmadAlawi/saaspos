<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds a few widely-used Latin American currencies that were missing from
 * the seeded set — Colombian Peso (COP), Chilean Peso (CLP), Peruvian Sol
 * (PEN). Ships as a migration (not just a seeder change) so existing
 * installs pick them up on the next update via the normal migrate step,
 * not only fresh installs. Idempotent — mirrors CurrenciesSeeder.
 */
return new class extends Migration
{
    private array $rows = [
        ['code' => 'COP', 'name' => 'Colombian Peso', 'symbol' => 'COL$', 'decimals' => 2, 'symbol_first' => true, 'thousands_separator' => '.', 'decimal_separator' => ','],
        ['code' => 'CLP', 'name' => 'Chilean Peso',   'symbol' => 'CLP$', 'decimals' => 0, 'symbol_first' => true, 'thousands_separator' => '.', 'decimal_separator' => ','],
        ['code' => 'PEN', 'name' => 'Peruvian Sol',   'symbol' => 'S/',   'decimals' => 2, 'symbol_first' => true, 'thousands_separator' => ',', 'decimal_separator' => '.'],
    ];

    public function up(): void
    {
        foreach ($this->rows as $row) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name'                => $row['name'],
                    'symbol'              => $row['symbol'],
                    'decimals'            => $row['decimals'],
                    'symbol_first'        => $row['symbol_first'],
                    'thousands_separator' => $row['thousands_separator'],
                    'decimal_separator'   => $row['decimal_separator'],
                    'is_active'           => true,
                ],
            );
        }
    }

    public function down(): void
    {
        // Only remove ones not adopted as a company/store currency, so a
        // rollback can't orphan money records.
        foreach ($this->rows as $row) {
            $inUse = DB::table('company')->where('base_currency_code', $row['code'])->exists()
                || DB::table('stores')->where('currency_code', $row['code'])->exists();

            if (! $inUse) {
                DB::table('currencies')->where('code', $row['code'])->delete();
            }
        }
    }
};
