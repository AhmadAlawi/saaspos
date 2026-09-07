<?php

namespace App\Actions\Stores;

use App\Exceptions\StoreNotDeactivatable;
use App\Models\Purchase;
use App\Models\Store;

/**
 * Flip a store's active flag. Deactivated stores keep their data and
 * stay visible in the switcher (read-only), but accept no new
 * transactions. Reactivating just sets the flag back.
 *
 * Guards on deactivation (docs/features/multi-store.md §6.5):
 *   - the company default store can't be deactivated — set another
 *     default first;
 *   - the last remaining active store can't be deactivated — an install
 *     must always have one store to transact against.
 *
 * Hook points:
 *   - action `store.before_deactivate` → ($store)
 *   - action `store.after_deactivate`  → ($store)
 *
 * @throws StoreNotDeactivatable
 */
class DeactivateStore
{
    public function __invoke(Store $store, bool $active = false): Store
    {
        if (! $active) {
            if ($store->is_default) {
                throw new StoreNotDeactivatable('default_store');
            }

            $otherActive = Store::query()
                ->where('is_active', true)
                ->whereKeyNot($store->getKey())
                ->exists();

            if (! $otherActive) {
                throw new StoreNotDeactivatable('last_active');
            }

            // Block deactivation when this store still has draft or
            // in-flight purchases — a clerk shouldn't be able to
            // silently retire a store mid-receive. `paid` and
            // `cancelled` are closed and don't count.
            $hasOpenPurchases = Purchase::query()
                ->where('store_id', $store->getKey())
                ->whereIn('status', [
                    Purchase::STATUS_DRAFT,
                    Purchase::STATUS_SUBMITTED,
                    Purchase::STATUS_RECEIVED,
                    Purchase::STATUS_PARTIALLY_PAID,
                ])
                ->exists();
            if ($hasOpenPurchases) {
                throw new StoreNotDeactivatable('has_open_purchases');
            }

            do_action('store.before_deactivate', $store);
        }

        $store->update(['is_active' => $active]);

        if (! $active) {
            do_action('store.after_deactivate', $store);
        }

        return $store;
    }
}
