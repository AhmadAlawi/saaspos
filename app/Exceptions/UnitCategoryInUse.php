<?php

namespace App\Exceptions;

use RuntimeException;

class UnitCategoryInUse extends RuntimeException
{
    public function __construct(public readonly int $count)
    {
        parent::__construct("Unit category is in use by {$count} unit(s).");
    }
}
