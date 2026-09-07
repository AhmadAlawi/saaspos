<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Applied to every store-scoped model (stock levels, movements, sales,
 * shifts, journal entries — see docs/features/multi-store.md §5).
 *
 * Three responsibilities:
 *   1. A global `store` scope filtering `store_id = current_store_id()`.
 *   2. Auto-fill `store_id` on create when the caller didn't set it.
 *   3. A `withoutStoreScope()` convenience for cross-store queries.
 *
 * When `current_store_id()` is null (seeding, console, installer) the
 * filter is skipped and auto-fill does nothing — global tooling sees and
 * writes every row, and the caller is responsible for setting store_id.
 */
trait StoreScoped
{
    public static function bootStoreScoped(): void
    {
        static::addGlobalScope('store', new class implements Scope {
            public function apply(Builder $builder, Model $model): void
            {
                if ($id = current_store_id()) {
                    $builder->where($model->getTable().'.store_id', $id);
                }
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('store_id') === null && ($id = current_store_id())) {
                $model->setAttribute('store_id', $id);
            }
        });
    }

    /**
     * Query without the store filter — for cross-store reports and admin
     * tooling. Always pair with an explicit, authorized `whereIn('store_id', …)`.
     *
     * @return Builder<static>
     */
    public static function withoutStoreScope(): Builder
    {
        return static::withoutGlobalScope('store');
    }
}
