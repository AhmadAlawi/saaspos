<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\AccountMapping;

/**
 * Resolves a business-event mapping key (e.g. `sales_revenue`, `cogs`,
 * `accounts_payable_suppliers`) to the {@see Account} an auto-posted journal
 * entry should hit. A store-specific mapping wins over the global default; the
 * `account.mapping.resolve` filter lets plugins override the routing.
 */
class AccountMappingResolver
{
    public function resolve(string $key, ?int $storeId = null): Account
    {
        $account = null;

        if ($storeId !== null) {
            $account = AccountMapping::where('key', $key)->where('store_id', $storeId)->first()?->account;
        }

        if (! $account) {
            $account = AccountMapping::where('key', $key)->whereNull('store_id')->first()?->account;
        }

        $account = apply_filters('account.mapping.resolve', $account, $key, $storeId);

        if (! $account instanceof Account) {
            throw new \RuntimeException("No account mapped for key [{$key}].");
        }

        return $account;
    }

    /** Convenience: the mapped account's id. */
    public function id(string $key, ?int $storeId = null): int
    {
        return $this->resolve($key, $storeId)->id;
    }
}
