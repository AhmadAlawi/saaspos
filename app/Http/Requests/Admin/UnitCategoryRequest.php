<?php

namespace App\Http\Requests\Admin;

use App\Models\UnitCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('unitCategory');
        $id = $category instanceof UnitCategory ? $category->id : null;

        return [
            'name' => ['required', 'string', 'max:64', Rule::unique('unit_categories', 'name')->ignore($id)],
            'slug' => [
                'required',
                'string',
                'max:32',
                'alpha_dash',
                Rule::unique('unit_categories', 'slug')->ignore($id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }

    public function persistedAttributes(): array
    {
        $data = $this->validated();
        $data['is_active']  = $this->boolean('is_active', true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        return $data;
    }
}
