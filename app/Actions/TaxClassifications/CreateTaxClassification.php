<?php

namespace App\Actions\TaxClassifications;

use App\Models\TaxClassification;

class CreateTaxClassification
{
    public function __invoke(array $attrs): TaxClassification
    {
        return TaxClassification::create($attrs);
    }
}
