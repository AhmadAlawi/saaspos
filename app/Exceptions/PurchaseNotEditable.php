<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an edit / delete is attempted on a purchase that's no
 * longer in `draft` status. Caught by the controller and surfaced as
 * a friendly flash so the user knows to use the receive/cancel/return
 * flow instead.
 */
class PurchaseNotEditable extends RuntimeException
{
    public function __construct(public readonly string $status)
    {
        parent::__construct("Purchase is in '{$status}' state and cannot be edited.");
    }
}
