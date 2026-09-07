<?php

namespace App\Http\Requests\Admin;

use App\Models\CustomerGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $row = $this->route('customerGroup');
        $id  = $row instanceof CustomerGroup ? $row->id : null;

        return [
            'name'                     => ['required', 'string', 'max:100', Rule::unique('customer_groups', 'name')->ignore($id)],
            'default_discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'is_active'                => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function persistedAttributes(): array
    {
        $data = $this->validated();
        $data['is_active'] = $this->boolean('is_active', true);
        $data['default_discount_percent'] = $data['default_discount_percent'] ?? null;
        return $data;
    }
}
