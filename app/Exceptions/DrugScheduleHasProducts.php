<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see App\Actions\DrugSchedules\DeleteDrugSchedule} when
 * the schedule is still referenced from `products.pharmacy_schedule`.
 * Deleting would orphan those records' compliance metadata.
 */
class DrugScheduleHasProducts extends RuntimeException
{
    public function __construct(public readonly int $count)
    {
        parent::__construct("Cannot delete drug schedule — {$count} product(s) reference it.");
    }
}
