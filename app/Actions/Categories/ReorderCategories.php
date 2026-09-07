<?php

namespace App\Actions\Categories;

use App\Events\CategoriesReordered;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Persist a new top-to-bottom order for categories AND optionally their
 * parent assignments. The payload is an array of rows in display order:
 *
 *   [
 *     ['id' => 1, 'parent_id' => null],
 *     ['id' => 5, 'parent_id' => 1],
 *     ...
 *   ]
 *
 * Rows arrive top-to-bottom. Because `Category::scopeOrdered()` sorts
 * DESC, the top row must carry the HIGHEST sort_order, so we assign
 * `sort_order = count - position` (N, N-1, … 1). When `parent_id` is
 * included we also update it — used by tree-view drag to move a row
 * under a new parent. The combined payload is validated against cycles
 * before any DB writes happen.
 */
class ReorderCategories
{
    /**
     * @param array<int, array{id:int, parent_id:?int}> $rows
     *
     * @throws RuntimeException when the proposed structure contains a cycle.
     */
    public function __invoke(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $this->assertNoCycles($rows);

        do_action('categories.before_reorder', $rows);

        DB::transaction(function () use ($rows) {
            $total = count($rows);
            foreach ($rows as $position => $row) {
                // scopeOrdered() sorts DESC, so the first (top) row must
                // carry the HIGHEST sort_order. Assign N, N-1, … 1.
                $update = ['sort_order' => $total - $position];
                if (\array_key_exists('parent_id', $row)) {
                    $update['parent_id'] = $row['parent_id'];
                }
                Category::query()->whereKey($row['id'])->update($update);
            }
        });

        do_action('categories.reordered', $rows);
        event(new CategoriesReordered($rows));
    }

    /**
     * Walk the proposed parent_id chain of every row. If any chain
     * loops back on itself, the structure is invalid.
     *
     * @param array<int, array{id:int, parent_id:?int}> $rows
     */
    private function assertNoCycles(array $rows): void
    {
        $parentMap = [];
        foreach ($rows as $row) {
            $parentMap[$row['id']] = $row['parent_id'] ?? null;
        }

        foreach (array_keys($parentMap) as $id) {
            $cursor = $parentMap[$id];
            $seen   = [$id => true];
            while ($cursor !== null) {
                if (isset($seen[$cursor])) {
                    throw new RuntimeException('Cycle detected in proposed category hierarchy.');
                }
                $seen[$cursor] = true;
                $cursor        = $parentMap[$cursor] ?? null;
            }
        }
    }
}
