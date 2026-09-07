<?php

namespace App\Actions\Accounting;

use App\Exceptions\AccountProtected;
use App\Models\Account;
use App\Services\Accounting\AccountUsage;

/**
 * Deletes a custom ledger account. Refused for system accounts, accounts that
 * a posted entry references (would orphan history), and accounts that back a
 * business mapping (would break auto-posting). Fires `account.after_delete`.
 */
class DeleteAccount
{
    public function __construct(private AccountUsage $usage) {}

    public function __invoke(Account $account): void
    {
        if ($account->is_system) {
            throw new AccountProtected(__('accounting.chart.errors.delete_system'));
        }
        if ($this->usage->postedLineCount($account) > 0) {
            throw new AccountProtected(__('accounting.chart.errors.delete_in_use'));
        }
        if ($this->usage->mappingCount($account) > 0) {
            throw new AccountProtected(__('accounting.chart.errors.delete_mapped'));
        }

        $account->delete();

        do_action('account.after_delete', $account);
    }
}
