<?php

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;

/**
 * Stock adjustments hold the keys to the inventory ledger — anyone with
 * `products.adjust_stock` can create, post, and delete drafts. View is
 * gated on the lighter `products.view`. Editing a POSTED adjustment is
 * never allowed (handled by the controller, not here).
 */
class StockAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('products.view');
    }

    public function view(User $user, StockAdjustment $adj): bool
    {
        return $user->hasPermission('products.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('products.adjust_stock');
    }

    public function update(User $user, StockAdjustment $adj): bool
    {
        return $user->hasPermission('products.adjust_stock') && $adj->isDraft();
    }

    public function post(User $user, StockAdjustment $adj): bool
    {
        return $user->hasPermission('products.adjust_stock') && $adj->isDraft();
    }

    public function delete(User $user, StockAdjustment $adj): bool
    {
        return $user->hasPermission('products.adjust_stock') && $adj->isDraft();
    }
}
