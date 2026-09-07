<?php

namespace App\Policies;

use App\Models\CustomerGroup;
use App\Models\User;

/**
 * Customer-groups admin is a sub-setting of the customer module, so it
 * reuses `customers.update` per the feature doc §12.3. Anyone who can
 * edit customers can edit the groups they belong to.
 */
class CustomerGroupPolicy
{
    public function viewAny(User $user): bool   { return $user->hasPermission('customers.view'); }
    public function view(User $user, CustomerGroup $g): bool   { return $user->hasPermission('customers.view'); }
    public function create(User $user): bool    { return $user->hasPermission('customers.update'); }
    public function update(User $user, CustomerGroup $g): bool { return $user->hasPermission('customers.update'); }
    public function delete(User $user, CustomerGroup $g): bool { return $user->hasPermission('customers.update'); }
}
