<?php

namespace App\Actions\Accounting;

use App\Exceptions\AccountGroupProtected;
use App\Models\AccountGroup;

/**
 * Deletes a custom, empty group. Refused for system groups and for any group
 * that still holds accounts or sub-groups — those must be moved or removed
 * first. Fires `account_group.after_delete`.
 */
class DeleteAccountGroup
{
    public function __invoke(AccountGroup $group): void
    {
        if ($group->is_system) {
            throw new AccountGroupProtected(__('accounting.chart.groups.errors.delete_system'));
        }
        if ($group->children()->exists()) {
            throw new AccountGroupProtected(__('accounting.chart.groups.errors.delete_has_children'));
        }
        if ($group->accounts()->exists()) {
            throw new AccountGroupProtected(__('accounting.chart.groups.errors.delete_has_accounts'));
        }

        $group->delete();

        do_action('account_group.after_delete', $group);
    }
}
