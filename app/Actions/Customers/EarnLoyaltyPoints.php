<?php

namespace App\Actions\Customers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerCreditTransaction;
use App\Models\Sale;
use App\Services\Wallet\PassFastClient;

/**
 * Awards loyalty points for a just-completed sale — registered on the
 * existing `sale.after_complete` action ({@see \App\Providers\HookServiceProvider}),
 * same best-effort try/catch-and-report() wrapping every other post-sale
 * side effect there uses. A failure here never blocks or reverses the
 * sale itself.
 *
 * Basis is `sale.subtotal` — post-discount, pre-tax (the same figure
 * {@see \App\Actions\Sales\PriceCart} already treats as "what the
 * customer actually paid for the goods"), NOT `grand_total` (which
 * would earn points on tax) and NOT the pre-discount gross (which
 * would earn points on money the customer didn't spend).
 */
class EarnLoyaltyPoints
{
    public function handle(Sale $sale): void
    {
        if (! $sale->customer_id) {
            return;
        }

        $company = Company::current();
        if (! $company?->loyalty_enabled) {
            return;
        }

        $earnRate = (string) $company->loyalty_earn_rate;
        if (bccomp($earnRate, '0', 4) <= 0) {
            return;
        }

        $points = (int) floor((float) bcmul((string) $sale->subtotal, $earnRate, 4));
        if ($points <= 0) {
            return;
        }

        $customer = Customer::query()->lockForUpdate()->find($sale->customer_id);
        if (! $customer) {
            return;
        }

        $customer->increment('loyalty_points', $points);

        CustomerCreditTransaction::create([
            'customer_id'           => $customer->id,
            'store_id'              => $sale->store_id,
            'type'                  => CustomerCreditTransaction::TYPE_LOYALTY_EARN,
            'points'                => $points,
            'balance_after_points'  => $customer->loyalty_points,
            'reference_type'        => Sale::class,
            'reference_id'          => $sale->id,
            'created_by'            => $sale->cashier_id,
        ]);

        $this->syncWalletBalance($customer, $company);
    }

    /**
     * Refreshes the points figure on an already-issued PassFast pass —
     * only relevant once the customer has actually downloaded one
     * (no `wallet_pass_token` means they never have). Best-effort, same
     * as every other step in this action.
     */
    private function syncWalletBalance(Customer $customer, Company $company): void
    {
        if (! $customer->wallet_pass_token || ! PassFastClient::isConfigured($company)) {
            return;
        }

        try {
            app(PassFastClient::class)->updateBalance($customer, $customer->wallet_pass_token);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
