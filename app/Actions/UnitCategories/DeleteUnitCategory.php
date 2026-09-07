<?php

namespace App\Actions\UnitCategories;

use App\Exceptions\UnitCategoryInUse;
use App\Models\Unit;
use App\Models\UnitCategory;

class DeleteUnitCategory
{
    public function __invoke(UnitCategory $category): void
    {
        $count = Unit::where('category', $category->slug)->count();
        if ($count > 0) {
            throw new UnitCategoryInUse($count);
        }
        $category->delete();
    }
}
