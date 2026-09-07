<?php

namespace App\Actions\UnitCategories;

use App\Models\UnitCategory;

class CreateUnitCategory
{
    public function __invoke(array $attrs): UnitCategory
    {
        return UnitCategory::create($attrs);
    }
}
