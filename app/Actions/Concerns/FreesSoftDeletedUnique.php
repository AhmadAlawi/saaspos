<?php

namespace App\Actions\Concerns;

/**
 * Frees unique column values held by SOFT-DELETED rows so a fresh insert (or
 * a rename) doesn't collide with the DB-level unique index.
 *
 * MySQL unique indexes span every row, including soft-deleted ones, but our
 * Form Requests validate uniqueness only among non-trashed rows
 * (`Rule::unique(...)->whereNull('deleted_at')`) — deleting a record is meant
 * to free its email / code / sku for reuse. Nothing freed the value on delete,
 * so reusing a deleted one passed validation then hit a raw 1062 at insert.
 *
 * This reclaims it: the matching trashed rows are kept (stock / sales / other
 * history may reference them by id) but their conflicting unique values are
 * tombstoned to a guaranteed-unique `__del<id>` marker. The fresh row then
 * inserts cleanly.
 *
 * System-wide rule — see the `feedback-soft-delete-unique` memory. Mirrors the
 * products-specific {@see \App\Actions\Products\Concerns\ReclaimsSoftDeletedIdentifiers}.
 */
trait FreesSoftDeletedUnique
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  array<string, mixed>  $match  column => incoming value used to find clashing trashed rows
     * @param  list<string>          $free   columns to tombstone on each matched row (defaults to the match keys)
     * @param  int|null              $exceptId  a row id to leave alone (the one being updated)
     */
    protected function freeSoftDeletedUnique(string $modelClass, array $match, array $free = [], ?int $exceptId = null): void
    {
        $match = array_filter($match, static fn ($v) => $v !== null && $v !== '');
        if ($match === []) {
            return;
        }

        $columns = $free !== [] ? $free : array_keys($match);

        $trashed = $modelClass::onlyTrashed()
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where(function ($q) use ($match) {
                foreach ($match as $col => $val) {
                    $q->orWhere($col, $val);
                }
            })
            ->get();

        foreach ($trashed as $dead) {
            $marker = '__del'.$dead->getKey();   // ≤ ~15 chars — fits every unique column
            $update = [];
            foreach ($columns as $col) {
                if ($dead->{$col} !== null && $dead->{$col} !== '') {
                    $update[$col] = $marker;
                }
            }
            if ($update !== []) {
                $dead->forceFill($update)->saveQuietly();
            }
        }
    }
}
