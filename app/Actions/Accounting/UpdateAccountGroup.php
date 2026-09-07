<?php

namespace App\Actions\Accounting;

use App\Exceptions\AccountGroupProtected;
use App\Models\AccountGroup;

/**
 * Renames a group and — for custom groups — re-files it under a different
 * parent. Reparenting is constrained so the chart stays sound:
 *   - system groups can be renamed but not moved;
 *   - the new parent must share the group's reporting type (so accounts under
 *     it keep their asset/liability/… classification);
 *   - a group can't be moved under itself or one of its descendants.
 * Fires `account_group.after_update`.
 */
class UpdateAccountGroup
{
    public function __invoke(AccountGroup $group, array $data): AccountGroup
    {
        $attrs = ['name' => trim((string) $data['name'])];

        if (array_key_exists('parent_id', $data)) {
            $newParentId = ($data['parent_id'] === null || $data['parent_id'] === '')
                ? null
                : (int) $data['parent_id'];

            if ($newParentId !== $group->parent_id) {
                $this->assertMovable($group, $newParentId);
                $attrs['parent_id'] = $newParentId;
            }
        }

        $group->update($attrs);

        do_action('account_group.after_update', $group);

        return $group;
    }

    private function assertMovable(AccountGroup $group, ?int $newParentId): void
    {
        if ($group->is_system) {
            throw new AccountGroupProtected(__('accounting.chart.groups.errors.system_move'));
        }
        if ($group->parent_id === null || $newParentId === null) {
            throw new AccountGroupProtected(__('accounting.chart.groups.errors.root_move'));
        }

        $newParent = AccountGroup::findOrFail($newParentId);

        if ($newParent->type !== $group->type) {
            throw new AccountGroupProtected(__('accounting.chart.groups.errors.cross_type'));
        }
        if ($newParentId === $group->id || $this->isDescendant($group, $newParentId)) {
            throw new AccountGroupProtected(__('accounting.chart.groups.errors.cycle'));
        }
    }

    /** Is $candidateId somewhere inside $group's own subtree? */
    private function isDescendant(AccountGroup $group, int $candidateId): bool
    {
        $frontier = [$group->id];

        while ($frontier !== []) {
            $children = AccountGroup::whereIn('parent_id', $frontier)->pluck('id')->all();
            if (in_array($candidateId, array_map('intval', $children), true)) {
                return true;
            }
            $frontier = $children;
        }

        return false;
    }
}
