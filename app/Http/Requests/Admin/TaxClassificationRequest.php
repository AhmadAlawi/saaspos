<?php

namespace App\Http\Requests\Admin;

use App\Models\TaxClassification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxClassificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $classification = $this->route('taxClassification');
        $id = $classification instanceof TaxClassification ? $classification->id : null;

        return [
            'name'        => ['required', 'string', 'max:100'],
            'slug'        => [
                'required', 'string', 'max:50', 'alpha_dash',
                Rule::unique('tax_classifications', 'slug')->ignore($id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'   => ['sometimes', 'boolean'],
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
