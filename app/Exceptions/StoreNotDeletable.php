<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see App\Actions\Stores\DeleteStore} when a store cannot be
 * removed. v1.0 only blocks deleting the company's last store — an
 * install must always have at least one. Once shifts / transfers /
 * sales land, this exception also guards those (open shifts, in-transit
 * transfers, draft transactions — see docs/features/multi-store.md §6.5).
 */
class StoreNotDeletable extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Cannot delete store: {$reason}");
    }
}
