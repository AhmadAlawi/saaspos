<?php

namespace App\Actions\TaxClassifications;

use App\Models\TaxClassification;

class UpdateTaxClassification
{
    public function __invoke(TaxClassification $classification, array $attrs): TaxClassification
    {
        $classification->update($attrs);
        return $classification;
    }
}
