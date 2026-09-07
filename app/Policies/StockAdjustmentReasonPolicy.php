<?php

namespace App\Policies;

use App\Models\StockAdjustmentReason;
use App\Models\User;

/**
 * Reasons are the picklist for stock adjustments, so we gate them on
 * the same `products.adjust_stock` permission that opens the adjustment
 * editor — anyone who can post an adjustment can manage the catalogue
 * of "why".
 */
class StockAdjustmentReasonPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('products.adjust_stock'); }
    public function view(User $user, StockAdjustmentReason $r): bool { return $user->hasPermission('products.adjust_stock'); }
    public function create(User $user): bool { return $user->hasPermission('products.adjust_stock'); }
    public function update(User $user, StockAdjustmentReason $r): bool { return $user->hasPermission('products.adjust_stock'); }
    public function delete(User $user, StockAdjustmentReason $r): bool { return $user->hasPermission('products.adjust_stock'); }
}
