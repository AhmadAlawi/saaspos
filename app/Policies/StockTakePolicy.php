<?php

namespace App\Policies;

use App\Models\StockTake;
use App\Models\User;

/**
 * Stock takes write into the same ledger as adjustments, so they share
 * the `products.adjust_stock` permission. View is gated on the lighter
 * `products.view`. Editing or posting a CANCELLED/POSTED take is never
 * allowed — that state guard is enforced in the controller as well.
 */
class StockTakePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('products.view');
    }

    public function view(User $user, StockTake $take): bool
    {
        return $user->hasPermission('products.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('products.adjust_stock');
    }

    public function update(User $user, StockTake $take): bool
    {
        return $user->hasPermission('products.adjust_stock') && $take->isDraft();
    }

    public function post(User $user, StockTake $take): bool
    {
        return $user->hasPermission('products.adjust_stock') && $take->isDraft();
    }

    public function delete(User $user, StockTake $take): bool
    {
        return $user->hasPermission('products.adjust_stock') && $take->isDraft();
    }
}
