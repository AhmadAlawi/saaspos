<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use Illuminate\Support\Str;

/**
 * Lazily generates the opaque token behind a customer's Apple Wallet
 * download link ({@see \App\Http\Controllers\WalletPassController}) —
 * most customers never need one, so there's no reason to backfill every
 * row up front.
 */
class EnsureWalletPassToken
{
    public function handle(Customer $customer): string
    {
        if ($customer->wallet_pass_token) {
            return $customer->wallet_pass_token;
        }

        do {
            $token = Str::random(48);
        } while (Customer::query()->where('wallet_pass_token', $token)->exists());

        $customer->forceFill(['wallet_pass_token' => $token])->save();

        return $token;
    }
}
