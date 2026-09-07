<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see App\Actions\Units\DeleteUnit} when a unit is still
 * referenced by live products OR by other (derived) units. Reassign
 * those references first.
 *
 * `$kind` is either 'product' or 'unit' so the message can be specific.
 */
class UnitHasProducts extends RuntimeException
{
    public function __construct(public readonly int $count, public readonly string $kind = 'product')
    {
        parent::__construct("Cannot delete unit — {$count} {$kind}(s) still reference it.");
    }
}
