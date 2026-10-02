<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use Illuminate\Support\Str;

/**
 * `customers.code` already existed (free-text, admin-editable, unique)
 * before loyalty scan-lookup needed it — most customers never had one
 * set. This lazily generates one the first time something needs to
 * scan-identify a customer (a wallet pass request, or an admin printing
 * a loyalty card) rather than requiring every customer to be manually
 * assigned a code up front.
 */
class EnsureLoyaltyCode
{
    public function handle(Customer $customer): string
    {
        if ($customer->code) {
            return $customer->code;
        }

        do {
            $code = Str::upper(Str::random(8));
        } while (Customer::query()->where('code', $code)->exists());

        $customer->forceFill(['code' => $code])->save();

        return $code;
    }
}
