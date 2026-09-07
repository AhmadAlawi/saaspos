<?php

namespace App\Actions\UnitCategories;

use App\Models\UnitCategory;

class UpdateUnitCategory
{
    public function __invoke(UnitCategory $category, array $attrs): UnitCategory
    {
        $category->update($attrs);
        return $category;
    }
}
