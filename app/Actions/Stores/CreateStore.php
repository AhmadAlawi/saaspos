<?php

namespace App\Actions\Stores;

use App\Actions\Concerns\FreesSoftDeletedUnique;
use App\Models\Store;
use App\Models\User;

/**
 * Persist a new store.
 *
 * Hook points (docs/features/multi-store.md §15):
 *   - filter `store.attributes`     → modify the attribute array
 *   - action `store.before_create`  → ($data)
 *   - action `store.after_create`   → ($store)
 *
 * If a creator is supplied, they're assigned to the new store (a user
 * who creates a branch obviously needs access to it).
 */
class CreateStore
{
    use FreesSoftDeletedUnique;

    /** @param array<string, mixed> $data Already-validated payload from StoreRequest. */
    public function __invoke(array $data, ?User $creator = null): Store
    {
        $data = apply_filters('store.attributes', $data);
        do_action('store.before_create', $data);

        // Free a deleted store's code so the unique index doesn't 1062.
        $this->freeSoftDeletedUnique(Store::class, ['code' => $data['code'] ?? null]);

        $store = Store::create($data);

        // The company must always have exactly one default store. If none
        // exists yet (first store created), this one becomes it.
        if (Store::query()->where('is_default', true)->doesntExist()) {
            $store->forceFill(['is_default' => true])->save();
        }

        if ($creator && ! $creator->is_super_admin && ($roleId = $this->defaultRoleId())) {
            $store->users()->syncWithoutDetaching([$creator->id => ['role_id' => $roleId]]);
        }

        do_action('store.after_create', $store);

        return $store;
    }

    /**
     * The role to grant the creator on a new store — the built-in Admin
     * role, falling back to the first defined role. Null when no roles
     * exist yet (the assignment is then skipped — super admins reach
     * every store regardless).
     */
    private function defaultRoleId(): ?int
    {
        return \Illuminate\Support\Facades\DB::table('roles')->where('name', 'Admin')->value('id')
            ?? \Illuminate\Support\Facades\DB::table('roles')->orderBy('id')->value('id');
    }
}
