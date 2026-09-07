<?php

namespace App\Policies;

use App\Models\DrugSchedule;
use App\Models\User;

/**
 * Drug-schedule authorization. Schedules are pharmacy-compliance lookup
 * data feeding the Products compliance tab, so they ride the same
 * low-risk `taxonomies.manage` permission as the other catalog lookups.
 * Super admins bypass via Gate::before.
 */
class DrugSchedulePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('taxonomies.manage'); }
    public function view(User $user, DrugSchedule $row): bool { return $user->hasPermission('taxonomies.manage'); }
    public function create(User $user): bool { return $user->hasPermission('taxonomies.manage'); }
    public function update(User $user, DrugSchedule $row): bool { return $user->hasPermission('taxonomies.manage'); }
    public function delete(User $user, DrugSchedule $row): bool { return $user->hasPermission('taxonomies.manage'); }
}
