<?php

namespace App\Policies;

use App\Models\PurchasePayment;
use App\Models\User;

/**
 * Supplier-payment authorization. Permissions seeded by PermissionsSeeder:
 *   - `suppliers.view`           — list/view payments
 *   - `suppliers.payments_record`— create
 *   - `suppliers.payments_void`  — void (Slice 4b)
 *
 * Super admins bypass via Gate::before.
 */
class PurchasePaymentPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('suppliers.view'); }
    public function view(User $user, PurchasePayment $p): bool { return $user->hasPermission('suppliers.view'); }
    public function create(User $user): bool { return $user->hasPermission('suppliers.payments_record'); }
    public function void(User $user, PurchasePayment $p): bool { return $user->hasPermission('suppliers.payments_void'); }
}
