<?php

namespace App\Exceptions;

use RuntimeException;

class TaxClassificationInUse extends RuntimeException
{
    public function __construct(public readonly int $count)
    {
        parent::__construct("Tax classification is in use by {$count} tax group(s).");
    }
}
