<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\PaymentMethod;

/**
 * Resolves which ledger account a tender lands in. A payment method can name
 * its account explicitly (`payment_methods.accounting_account_id`); otherwise
 * we fall back by type — cash → Cash on Hand, cheque → Cheques in Hand (via
 * the mapping), everything else → Cash at Bank. Lets sales post before the
 * customer has wired every method to a specific bank account.
 */
class PaymentAccountResolver
{
    public function __construct(private AccountMappingResolver $mappings) {}

    public function resolve(?PaymentMethod $method, ?int $storeId = null): Account
    {
        if ($method?->accounting_account_id) {
            $account = Account::find($method->accounting_account_id);
            if ($account) {
                return $account;
            }
        }

        if ($method?->code === 'cheque') {
            return $this->mappings->resolve('cheque_in_hand', $storeId);
        }

        $code = $method?->type === 'cash' ? '1010' : '1020';

        return Account::where('code', $code)->first()
            ?? Account::where('code', '1010')->firstOrFail();
    }
}
