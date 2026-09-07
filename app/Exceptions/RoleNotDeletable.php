<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see App\Actions\Roles\DeleteRole}:
 *   - `assigned` — a role still assigned to one or more users can't be
 *     deleted until those users are reassigned to another role.
 */
class RoleNotDeletable extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $count = 0)
    {
        parent::__construct("Cannot delete role: {$reason}");
    }
}
