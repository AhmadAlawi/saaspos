<?php

namespace App\Policies;

use App\Models\SalePayment;
use App\Models\User;

/**
 * Sale-payment authorization. Permissions:
 *   - `customers.view`             — list/view customer payments
 *   - `customers.payments_record`  — create customer settlement payments
 *
 * Void is deferred to a follow-up slice. Super admins bypass via Gate::before.
 */
class SalePaymentPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('customers.view'); }
    public function view(User $user, SalePayment $p): bool { return $user->hasPermission('customers.view'); }
    public function create(User $user): bool { return $user->hasPermission('customers.payments_record'); }
}
