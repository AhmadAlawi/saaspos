<?php

namespace App\Actions\Customers;

use App\Exceptions\CustomerHasBalance;
use App\Models\Customer;

/**
 * Soft-delete a customer. Blocks when there's still money to settle —
 * an outstanding balance or store credit. The UI surfaces the block
 * and offers deactivation as an alternative (preserves history).
 *
 * Hooks:
 *   - action `customer.before_delete` ($customer)
 *   - action `customer.after_delete`  ($customer)
 */
class DeleteCustomer
{
    public function __invoke(Customer $customer): void
    {
        if ((float) $customer->outstanding_balance > 0) {
            throw new CustomerHasBalance('outstanding balance', (float) $customer->outstanding_balance);
        }
        if ((float) $customer->store_credit_balance > 0) {
            throw new CustomerHasBalance('store credit', (float) $customer->store_credit_balance);
        }

        do_action('customer.before_delete', $customer);

        $customer->delete();

        do_action('customer.after_delete', $customer);
    }
}
