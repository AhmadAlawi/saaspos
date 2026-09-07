<?php

namespace App\Actions\TaxClassifications;

use App\Exceptions\TaxClassificationInUse;
use App\Models\TaxClassification;

class DeleteTaxClassification
{
    public function __invoke(TaxClassification $classification): void
    {
        $count = $classification->taxGroups()->count();
        if ($count > 0) {
            throw new TaxClassificationInUse($count);
        }
        $classification->delete();
    }
}
