<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires after a successful drag-to-reorder or tree-move. `rows` is
 * the full payload that was persisted: `[['id' => N, 'parent_id' => N|null], ...]`
 * in their new display order.
 */
class CategoriesReordered
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<int, array{id:int, parent_id:?int}> $rows
     */
    public function __construct(public array $rows) {}
}
