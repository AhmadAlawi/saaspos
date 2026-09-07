<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by the user Actions for protected operations:
 *   - `self`              — you can't deactivate/delete your own account.
 *   - `last_super_admin`  — the last active super admin can't be removed
 *     or demoted (there must always be a way back in).
 */
class UserActionDenied extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("User action denied: {$reason}");
    }
}
