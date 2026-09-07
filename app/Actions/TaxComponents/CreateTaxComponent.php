<?php

namespace App\Actions\TaxComponents;

use App\Models\TaxComponent;

class CreateTaxComponent
{
    /** @param array<string, mixed> $data */
    public function __invoke(array $data): TaxComponent
    {
        $row = TaxComponent::create($data);
        do_action('tax_component.after_create', $row);
        return $row;
    }
}
