<?php

namespace App\Actions\TaxGroups;

use App\Models\TaxGroup;
use Illuminate\Support\Facades\DB;

class CreateTaxGroup
{
    public function __construct(private readonly SyncTaxGroupComponents $syncComponents) {}

    /**
     * @param array<string, mixed> $data
     * @param list<int> $componentIds
     */
    public function __invoke(array $data, array $componentIds): TaxGroup
    {
        return DB::transaction(function () use ($data, $componentIds) {
            // Only one group at a time can be flagged as default. When
            // this one claims the flag, drop it from every other.
            if (! empty($data['is_default'])) {
                TaxGroup::query()->where('is_default', true)->update(['is_default' => false]);
            }

            $group = TaxGroup::create($data);
            ($this->syncComponents)($group, $componentIds);

            do_action('tax_group.after_create', $group);
            return $group->fresh(['components']);
        });
    }
}
