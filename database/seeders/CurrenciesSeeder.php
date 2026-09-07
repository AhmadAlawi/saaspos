<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->currencies() as $row) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name'                 => $row['name'],
                    'symbol'               => $row['symbol'],
                    'decimals'             => $row['decimals'],
                    'symbol_first'         => $row['symbol_first'],
                    'thousands_separator'  => $row['thousands_separator'],
                    'decimal_separator'    => $row['decimal_separator'],
                    'is_active'            => $row['is_active'] ?? true,
                ],
            );
        }
    }

    /**
     * Curated set of widely-used currencies. Customers enable more as needed.
     */
    private function currencies(): array
    {
        return [
            // Anchor / reference
            ['code' => 'USD', 'name' => 'United States Dollar', 'symbol' => '$',    'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'EUR', 'name' => 'Euro',                 'symbol' => '€',    'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'GBP', 'name' => 'British Pound',        'symbol' => '£',    'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],

            // South & Southeast Asia
            ['code' => 'INR', 'name' => 'Indian Rupee',         'symbol' => '₹',    'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'PKR', 'name' => 'Pakistani Rupee',      'symbol' => '₨',    'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'BDT', 'name' => 'Bangladeshi Taka',     'symbol' => '৳',    'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'LKR', 'name' => 'Sri Lankan Rupee',     'symbol' => 'Rs',   'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'NPR', 'name' => 'Nepalese Rupee',       'symbol' => '₨',    'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah',    'symbol' => 'Rp',   'decimals' => 0, 'symbol_first' => true,  'thousands_separator' => '.', 'decimal_separator' => ','],
            ['code' => 'MYR', 'name' => 'Malaysian Ringgit',    'symbol' => 'RM',   'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'PHP', 'name' => 'Philippine Peso',      'symbol' => '₱',    'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'SGD', 'name' => 'Singapore Dollar',     'symbol' => 'S$',   'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'THB', 'name' => 'Thai Baht',            'symbol' => '฿',    'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'VND', 'name' => 'Vietnamese Dong',      'symbol' => '₫',    'decimals' => 0, 'symbol_first' => false, 'thousands_separator' => '.', 'decimal_separator' => ','],

            // East Asia
            ['code' => 'CNY', 'name' => 'Chinese Yuan',         'symbol' => '¥',    'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'JPY', 'name' => 'Japanese Yen',         'symbol' => '¥',    'decimals' => 0, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'KRW', 'name' => 'South Korean Won',     'symbol' => '₩',    'decimals' => 0, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'HKD', 'name' => 'Hong Kong Dollar',     'symbol' => 'HK$',  'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'TWD', 'name' => 'New Taiwan Dollar',    'symbol' => 'NT$',  'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],

            // Middle East
            ['code' => 'AED', 'name' => 'UAE Dirham',           'symbol' => 'AED',  'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'SAR', 'name' => 'Saudi Riyal',          'symbol' => 'SAR',  'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'QAR', 'name' => 'Qatari Riyal',         'symbol' => 'QAR',  'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'KWD', 'name' => 'Kuwaiti Dinar',        'symbol' => 'KWD',  'decimals' => 3, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'BHD', 'name' => 'Bahraini Dinar',       'symbol' => 'BHD',  'decimals' => 3, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'OMR', 'name' => 'Omani Rial',           'symbol' => 'OMR',  'decimals' => 3, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'JOD', 'name' => 'Jordanian Dinar',      'symbol' => 'JOD',  'decimals' => 3, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'EGP', 'name' => 'Egyptian Pound',       'symbol' => 'E£',   'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'TRY', 'name' => 'Turkish Lira',         'symbol' => '₺',    'decimals' => 2, 'symbol_first' => false, 'thousands_separator' => '.', 'decimal_separator' => ','],
            ['code' => 'ILS', 'name' => 'Israeli New Shekel',   'symbol' => '₪',    'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],

            // Africa
            ['code' => 'ZAR', 'name' => 'South African Rand',   'symbol' => 'R',    'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'NGN', 'name' => 'Nigerian Naira',       'symbol' => '₦',    'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'KES', 'name' => 'Kenyan Shilling',      'symbol' => 'KSh',  'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'GHS', 'name' => 'Ghanaian Cedi',        'symbol' => '₵',    'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],

            // Americas
            ['code' => 'CAD', 'name' => 'Canadian Dollar',      'symbol' => 'C$',   'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'MXN', 'name' => 'Mexican Peso',         'symbol' => 'Mex$', 'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'BRL', 'name' => 'Brazilian Real',       'symbol' => 'R$',   'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => '.', 'decimal_separator' => ','],
            ['code' => 'ARS', 'name' => 'Argentine Peso',       'symbol' => 'ARS$', 'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => '.', 'decimal_separator' => ','],
            ['code' => 'COP', 'name' => 'Colombian Peso',       'symbol' => 'COL$', 'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => '.', 'decimal_separator' => ','],
            ['code' => 'CLP', 'name' => 'Chilean Peso',         'symbol' => 'CLP$', 'decimals' => 0, 'symbol_first' => true,  'thousands_separator' => '.', 'decimal_separator' => ','],
            ['code' => 'PEN', 'name' => 'Peruvian Sol',         'symbol' => 'S/',   'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],

            // Oceania
            ['code' => 'AUD', 'name' => 'Australian Dollar',    'symbol' => 'A$',   'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],
            ['code' => 'NZD', 'name' => 'New Zealand Dollar',   'symbol' => 'NZ$',  'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => ',', 'decimal_separator' => '.'],

            // Europe (non-EUR)
            ['code' => 'CHF', 'name' => 'Swiss Franc',          'symbol' => 'CHF',  'decimals' => 2, 'symbol_first' => true,  'thousands_separator' => "'", 'decimal_separator' => '.'],
            ['code' => 'SEK', 'name' => 'Swedish Krona',        'symbol' => 'kr',   'decimals' => 2, 'symbol_first' => false, 'thousands_separator' => ' ', 'decimal_separator' => ','],
            ['code' => 'NOK', 'name' => 'Norwegian Krone',      'symbol' => 'kr',   'decimals' => 2, 'symbol_first' => false, 'thousands_separator' => ' ', 'decimal_separator' => ','],
            ['code' => 'DKK', 'name' => 'Danish Krone',         'symbol' => 'kr',   'decimals' => 2, 'symbol_first' => false, 'thousands_separator' => '.', 'decimal_separator' => ','],
            ['code' => 'PLN', 'name' => 'Polish Złoty',         'symbol' => 'zł',   'decimals' => 2, 'symbol_first' => false, 'thousands_separator' => ' ', 'decimal_separator' => ','],
            ['code' => 'CZK', 'name' => 'Czech Koruna',         'symbol' => 'Kč',   'decimals' => 2, 'symbol_first' => false, 'thousands_separator' => ' ', 'decimal_separator' => ','],
            ['code' => 'RUB', 'name' => 'Russian Ruble',        'symbol' => '₽',    'decimals' => 2, 'symbol_first' => false, 'thousands_separator' => ' ', 'decimal_separator' => ','],
        ];
    }
}
