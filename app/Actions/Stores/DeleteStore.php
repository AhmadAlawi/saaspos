<?php

namespace App\Actions\Stores;

use App\Exceptions\StoreNotDeletable;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete a store. Refused when:
 *   - it's the last store — an install must always keep one; or
 *   - it has transactional history (sales, purchases, stock movements,
 *     shifts, expenses, journal entries, transfers, …). Deleting a store
 *     that's referenced by financial/stock records would orphan reports
 *     and the audit trail (docs/features/multi-store.md §6.5). Deactivate
 *     it instead.
 *
 * Config-only references (per-store price overrides, terminals) are NOT
 * blockers — those are configuration, not activity.
 *
 * The activity check is schema-aware (Schema::hasTable/hasColumn) so it
 * stays correct as the Inventory / Sales / Purchases modules light up
 * their tables; tables that don't exist yet are simply skipped.
 *
 * Hook points:
 *   - action `store.before_delete` → ($store)
 *   - action `store.after_delete`  → ($store)
 *
 * @throws StoreNotDeletable
 */
class DeleteStore
{
    /**
     * Transactional, store-scoped tables keyed by `store_id`. A row in any
     * of these blocks deletion. Stock transfers (two store columns) are
     * handled separately below.
     */
    private const ACTIVITY_TABLES = [
        'sales', 'sale_returns', 'purchases',
        'shifts', 'cash_drawer_entries',
        'stock_movements', 'product_stock_levels', 'product_batches',
        'stock_adjustments',
        'expenses', 'recurring_expenses',
        'journal_entries',
    ];

    public function __invoke(Store $store): void
    {
        if (Store::query()->count() <= 1) {
            throw new StoreNotDeletable('last_store');
        }

        if ($this->hasActivity($store)) {
            throw new StoreNotDeletable('has_data');
        }

        do_action('store.before_delete', $store);

        $store->delete();

        do_action('store.after_delete', $store);
    }

    private function hasActivity(Store $store): bool
    {
        foreach (self::ACTIVITY_TABLES as $table) {
            if (Schema::hasTable($table)
                && Schema::hasColumn($table, 'store_id')
                && DB::table($table)->where('store_id', $store->id)->exists()) {
                return true;
            }
        }

        // Stock transfers reference two stores (sender + receiver).
        if (Schema::hasTable('stock_transfers')) {
            $referenced = DB::table('stock_transfers')
                ->where('from_store_id', $store->id)
                ->orWhere('to_store_id', $store->id)
                ->exists();
            if ($referenced) {
                return true;
            }
        }

        return false;
    }
}
