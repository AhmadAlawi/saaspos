<?php

namespace App\Events;

use App\Models\Category;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires after a category has been soft-deleted. `reparentedChildIds`
 * lists the child rows that just had their parent_id set to null, so
 * downstream listeners (search index, analytics rollups) can update
 * those rows too in one pass.
 */
class CategoryDeleted
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<int, int> $reparentedChildIds
     */
    public function __construct(
        public Category $category,
        public array $reparentedChildIds = [],
    ) {}
}
