<?php

namespace App\Actions\Installer;

use Illuminate\Support\Facades\DB;

class CreateCompanyAndStore
{
    /**
     * @param array{
     *     company_name: string,
     *     country_code: string,
     *     base_currency_code: string,
     *     timezone: string,
     *     industry: string,
     *     store_name: string,
     *     store_code: string,
     *     store_address: string,
     * } $data
     * @return array{company_id: int, store_id: int}
     */
    public function __invoke(array $data): array
    {
        $now = now();

        $companyId = DB::table('company')->insertGetId([
            'name'                      => $data['company_name'],
            'country_code'              => $data['country_code'],
            'base_currency_code'        => $data['base_currency_code'],
            'industry'                  => $data['industry'],
            'industries_enabled'        => json_encode([$data['industry']]),
            'fiscal_year_start_month'   => 4, // April; customer can change in Settings.
            'tax_registered'            => true,
            'composition_scheme_enabled'=> false,
            'created_at'                => $now,
            'updated_at'                => $now,
        ]);

        $storeId = DB::table('stores')->insertGetId([
            'code'                  => $data['store_code'],
            'name'                  => $data['store_name'],
            'address_line1'         => $data['store_address'],
            'country_code'          => $data['country_code'],
            'timezone'              => $data['timezone'],
            'currency_code'         => $data['base_currency_code'],
            'locale'                => 'en',
            'rounding_mode'         => 'half_up',
            'enforce_shifts'        => true,
            'tax_inclusive_pricing' => false,
            'cash_variance_tolerance' => 0,
            'pay_out_threshold'     => 0,
            'is_active'             => true,
            // The company's first store is its default (price-resolution
            // fallback + landing store). One install always has exactly one.
            'is_default'            => true,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);

        return ['company_id' => $companyId, 'store_id' => $storeId];
    }
}
