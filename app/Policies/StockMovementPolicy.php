<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

/**
 * Stock movements are the audit trail — readable by anyone with
 * `products.view`, never editable from the UI. Mistakes get corrected
 * by a counter-entry through the adjustment flow.
 */
class StockMovementPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('products.view'); }
    public function view(User $user, StockMovement $m): bool { return $user->hasPermission('products.view'); }
}
