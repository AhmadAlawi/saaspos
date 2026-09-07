<?php

namespace App\Actions\Stores;

use App\Models\Store;

/**
 * Update an existing store.
 *
 * Hook points:
 *   - action `store.before_update` → ($store, $data)
 *   - action `store.after_update`  → ($store)
 */
class UpdateStore
{
    /** @param array<string, mixed> $data Already-validated payload from StoreRequest. */
    public function __invoke(Store $store, array $data): Store
    {
        $data = apply_filters('store.attributes', $data, $store);
        do_action('store.before_update', $store, $data);

        $store->update($data);

        do_action('store.after_update', $store);

        return $store;
    }
}
