<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see App\Actions\Stores\DeactivateStore} when a store can't
 * be deactivated:
 *   - `last_active` — it's the only active store; an install must keep
 *     at least one store available to transact against.
 *   - `default_store` — it's the company default; pick another default
 *     first (the default store is always kept active).
 *   - `has_open_purchases` — it still has at least one purchase in
 *     draft / submitted / received / partially_paid status. Close
 *     those first, then deactivate.
 */
class StoreNotDeactivatable extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Cannot deactivate store: {$reason}");
    }
}
