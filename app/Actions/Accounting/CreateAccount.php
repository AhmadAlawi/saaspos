<?php

namespace App\Actions\Accounting;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Company;

/**
 * Creates a custom (non-system) ledger account under a chart-of-accounts
 * group. The account inherits its reporting `type` from the group and the
 * company base currency. Fires `account.after_create`.
 */
class CreateAccount
{
    public function __invoke(array $data): Account
    {
        $group = AccountGroup::findOrFail((int) $data['account_group_id']);

        $account = Account::create([
            'account_group_id' => $group->id,
            'code'             => trim((string) $data['code']),
            'name'             => trim((string) $data['name']),
            'type'             => $group->type,
            'currency_code'    => Company::query()->value('base_currency_code'),
            'is_system'        => false,
            'is_active'        => (bool) ($data['is_active'] ?? true),
        ]);

        do_action('account.after_create', $account);

        return $account;
    }
}
