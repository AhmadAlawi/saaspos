<?php

namespace App\Actions\Accounting;

use App\Models\JournalEntry;

/**
 * Posts the mirror image of an existing entry — every debit becomes a credit
 * and vice versa — via the canonical {@see PostJournalEntry}. A reversal of a
 * balanced entry is itself balanced, so this can undo any posted entry (a
 * voided sale, a corrected manual entry) without re-deriving the amounts.
 *
 * Links the two entries both ways (`reversal_of_id` / `reversed_by_id`).
 */
class ReverseJournalEntry
{
    public function __construct(private PostJournalEntry $post) {}

    /**
     * @param  array<string, mixed>  $overrides  merged over the reversal header
     *                                            (e.g. a different `source` or `entry_date`)
     */
    public function __invoke(JournalEntry $original, string $source, array $overrides = []): JournalEntry
    {
        $original->loadMissing('lines');

        $lines = $original->lines->map(fn ($l) => [
            'account_id'  => $l->account_id,
            'debit'       => (string) $l->credit, // swap
            'credit'      => (string) $l->debit,
            'description' => $l->description,
        ])->all();

        $reversal = ($this->post)(array_merge([
            'store_id'       => $original->store_id,
            'entry_date'     => now()->toDateString(),
            'source'         => $source,
            'reference_type' => $original->reference_type,
            'reference_id'   => $original->reference_id,
            'reversal_of_id' => $original->id,
            'description'    => __('accounting.descriptions.reversal', ['number' => $original->number]),
            'lines'          => $lines,
        ], $overrides));

        $original->forceFill(['reversed_by_id' => $reversal->id])->save();

        return $reversal;
    }
}
