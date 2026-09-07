<?php

namespace App\Policies;

use App\Models\Terminal;
use App\Models\User;

/**
 * Terminal authorization (docs/features/hardware.md §11). Viewing is
 * gated by `terminals.view`; creating, editing, configuring hardware,
 * and deleting all share `terminals.configure`. Super admins bypass via
 * AuthServiceProvider's Gate::before.
 */
class TerminalPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('terminals.view'); }

    public function view(User $user, Terminal $terminal): bool { return $user->hasPermission('terminals.view'); }

    public function create(User $user): bool { return $user->hasPermission('terminals.configure'); }

    public function update(User $user, Terminal $terminal): bool { return $user->hasPermission('terminals.configure'); }

    public function delete(User $user, Terminal $terminal): bool { return $user->hasPermission('terminals.configure'); }
}
