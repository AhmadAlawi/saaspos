<?php

namespace App\Actions\Stores;

use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Make a store the company default — the singleton `is_default` store
 * used as the price-resolution fallback and the landing store for new
 * users. Setting one clears the flag on every other store, all in one
 * transaction. The new default is forced active (a default store the
 * company can't transact against would be a footgun).
 *
 * Hook: action `store.default_changed` → ($store)
 */
class SetDefaultStore
{
    public function __invoke(Store $store): Store
    {
        DB::transaction(function () use ($store) {
            Store::query()
                ->where('is_default', true)
                ->whereKeyNot($store->getKey())
                ->update(['is_default' => false]);

            $store->forceFill(['is_default' => true, 'is_active' => true])->save();
        });

        do_action('store.default_changed', $store);

        return $store;
    }
}
