<?php

namespace App\Actions\TaxGroups;

use App\Models\TaxGroup;
use Illuminate\Support\Facades\DB;

class UpdateTaxGroup
{
    public function __construct(private readonly SyncTaxGroupComponents $syncComponents) {}

    /**
     * @param array<string, mixed> $data
     * @param list<int> $componentIds
     */
    public function __invoke(TaxGroup $group, array $data, array $componentIds): TaxGroup
    {
        return DB::transaction(function () use ($group, $data, $componentIds) {
            // Default-flag uniqueness (see CreateTaxGroup). Exclude self
            // so re-saving the same default doesn't toggle itself off.
            if (! empty($data['is_default'])) {
                TaxGroup::query()
                    ->where('id', '!=', $group->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $group->update($data);
            ($this->syncComponents)($group, $componentIds);

            do_action('tax_group.after_update', $group);
            return $group->fresh(['components']);
        });
    }
}
