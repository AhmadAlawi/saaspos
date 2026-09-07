<?php

namespace App\Policies;

use App\Models\StockLevel;
use App\Models\User;

/**
 * Stock levels are visible to anyone who can view products, but only
 * `products.adjust_stock` can change the reorder override (the only
 * field this screen mutates — actual stock changes go through an
 * adjustment, not a direct edit).
 */
class StockLevelPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('products.view'); }
    public function view(User $user, StockLevel $level): bool { return $user->hasPermission('products.view'); }
    public function update(User $user, StockLevel $level): bool { return $user->hasPermission('products.adjust_stock'); }
}
