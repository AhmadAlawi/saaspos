<?php

namespace App\Actions\TaxGroups;

use App\Models\TaxGroup;

/**
 * Sync the components attached to a tax group. The pivot order matters —
 * it drives the display order on receipts (India GST prints CGST → SGST →
 * Cess in that order). We persist `sort_order` from the input array's
 * position so the resolver iterates components deterministically.
 */
class SyncTaxGroupComponents
{
    /** @param list<int> $componentIds */
    public function __invoke(TaxGroup $group, array $componentIds): void
    {
        $payload = [];
        foreach (array_values($componentIds) as $idx => $id) {
            $payload[$id] = ['sort_order' => $idx + 1];
        }
        $group->components()->sync($payload);
    }
}
