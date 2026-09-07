<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown by {@see DeleteTaxGroup} when the group is referenced by any
 * product (directly or via category). Mirrors `CategoryHasProducts` —
 * the controller catches and offers a delete-with-move flow.
 */
class TaxGroupHasProducts extends Exception
{
    public readonly int $count;

    public function __construct(
        public readonly int $productCount,
        public readonly int $categoryCount,
    ) {
        $this->count = $productCount + $categoryCount;
        parent::__construct(
            'Tax group is in use by '.$productCount.' product(s) and '.$categoryCount.' category(s).'
        );
    }
}
