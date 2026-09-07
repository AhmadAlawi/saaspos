<?php

namespace App\Actions\Accounting;

use App\Models\AccountMapping;
use Illuminate\Support\Facades\DB;

/**
 * Re-points one or more business-event mappings (global, store_id = null) at
 * different accounts. Only touches rows that actually change. Fires
 * `account_mappings.updated`. Per-store overrides are a later concern — this
 * edits the global defaults every auto-posting action falls back to.
 *
 * @see \App\Services\Accounting\AccountMappingResolver
 */
class UpdateAccountMappings
{
    /**
     * @param  array<string, int>  $map  key => account_id
     * @return int  number of mappings actually changed
     */
    public function __invoke(array $map): int
    {
        $changed = 0;

        DB::transaction(function () use ($map, &$changed) {
            foreach ($map as $key => $accountId) {
                $accountId = (int) $accountId;
                if ($accountId <= 0) {
                    continue;
                }

                $mapping = AccountMapping::firstOrNew(['key' => (string) $key, 'store_id' => null]);
                if ((int) $mapping->account_id !== $accountId) {
                    $mapping->account_id = $accountId;
                    $mapping->save();
                    $changed++;
                }
            }
        });

        do_action('account_mappings.updated', $map);

        return $changed;
    }
}
