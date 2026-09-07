<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see App\Actions\Brands\DeleteBrand} when the row can't be
 * deleted for a structural reason:
 *   - `default_protected`     → the system-default "Generic" row
 *   - `replacement_is_self`   → caller passed the same id as the target
 *   - `replacement_not_found` → the picked replacement doesn't exist
 *
 * Translated to a 422 with a friendly message by the controller.
 */
class BrandNotDeletable extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Cannot delete brand — {$reason}.");
    }
}
