<?php

namespace App\Actions\Accounting;

use App\Exceptions\AccountProtected;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Services\Accounting\AccountUsage;

/**
 * Edits a ledger account's name / group / active flag, and its code while
 * still unused. Enforces the chart-of-accounts guards:
 *   - code is immutable once a posted entry references the account;
 *   - an account backing a business mapping can't be deactivated;
 *   - a system account can't be deactivated while it still carries a balance.
 * The `type` always follows the (possibly new) group. Fires
 * `account.after_update`.
 */
class UpdateAccount
{
    public function __construct(private AccountUsage $usage) {}

    public function __invoke(Account $account, array $data): Account
    {
        $group      = AccountGroup::findOrFail((int) $data['account_group_id']);
        $nextActive = (bool) ($data['is_active'] ?? false);

        // Deactivation guards.
        if ($account->is_active && ! $nextActive) {
            if ($this->usage->mappingCount($account) > 0) {
                throw new AccountProtected(__('accounting.chart.errors.deactivate_mapped'));
            }
            if ($account->is_system && bccomp($this->usage->balance($account), '0', 4) !== 0) {
                throw new AccountProtected(__('accounting.chart.errors.deactivate_balance'));
            }
        }

        $attrs = [
            'name'             => trim((string) $data['name']),
            'account_group_id' => $group->id,
            'type'             => $group->type,
            'is_active'        => $nextActive,
        ];

        // Code is immutable once referenced by a posted entry.
        $newCode = trim((string) ($data['code'] ?? ''));
        if ($newCode !== '' && $newCode !== $account->code) {
            if ($this->usage->postedLineCount($account) > 0) {
                throw new AccountProtected(__('accounting.chart.errors.code_locked'));
            }
            $attrs['code'] = $newCode;
        }

        $account->update($attrs);

        do_action('account.after_update', $account);

        return $account;
    }
}
