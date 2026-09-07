<?php

namespace App\Actions\TaxComponents;

use App\Exceptions\TaxComponentInUse;
use App\Models\TaxComponent;

class DeleteTaxComponent
{
    public function __invoke(TaxComponent $row): void
    {
        // Hard guard: a component referenced by any group cannot be
        // deleted. The group editor is where the user removes it from
        // groups; only then can it be deleted here. Mirrors the
        // Brand/Category "has products" pattern.
        $count = $row->groups()->count();
        if ($count > 0) {
            throw new TaxComponentInUse($count);
        }

        do_action('tax_component.before_delete', $row);
        $row->delete();
        do_action('tax_component.after_delete', $row);
    }
}
