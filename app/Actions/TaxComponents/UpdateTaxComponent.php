<?php

namespace App\Actions\TaxComponents;

use App\Models\TaxComponent;

class UpdateTaxComponent
{
    /** @param array<string, mixed> $data */
    public function __invoke(TaxComponent $row, array $data): TaxComponent
    {
        $row->update($data);
        do_action('tax_component.after_update', $row);
        return $row;
    }
}
