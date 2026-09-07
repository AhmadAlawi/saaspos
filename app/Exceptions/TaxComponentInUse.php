<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown by {@see DeleteTaxComponent} when the row participates in any
 * tax group. Users must remove it from every group's component list
 * before deleting it. Caught by the controller and surfaced as a 422.
 */
class TaxComponentInUse extends Exception
{
    public function __construct(public readonly int $count)
    {
        parent::__construct('Tax component is in use by '.$count.' group(s).');
    }
}
