<?php

namespace App\Actions\Accounting;

use App\Models\AccountGroup;

/**
 * Creates a custom (non-system) chart-of-accounts group under an existing
 * parent. The new group inherits the parent's reporting type, so the whole
 * hierarchy stays classified consistently. New top-level groups aren't
 * allowed — the five roots (Assets / Liabilities / Equity / Income /
 * Expenses) are fixed. Fires `account_group.after_create`.
 */
class CreateAccountGroup
{
    public function __invoke(array $data): AccountGroup
    {
        $parent = AccountGroup::findOrFail((int) $data['parent_id']);

        $group = AccountGroup::create([
            'parent_id'  => $parent->id,
            'name'       => trim((string) $data['name']),
            'type'       => $parent->type,
            'sort_order' => (int) AccountGroup::where('parent_id', $parent->id)->max('sort_order') + 1,
            'is_system'  => false,
        ]);

        do_action('account_group.after_create', $group);

        return $group;
    }
}
