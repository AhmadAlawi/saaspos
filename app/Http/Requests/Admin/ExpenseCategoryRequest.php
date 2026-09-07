<?php

namespace App\Http\Requests\Admin;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('expenseCategory');

        return $category instanceof ExpenseCategory
            ? ($this->user()?->can('update', $category) ?? false)
            : ($this->user()?->can('create', ExpenseCategory::class) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('expenseCategory')?->id;

        return [
            'name'      => [
                'required', 'string', 'max:100',
                Rule::unique('expense_categories', 'name')->ignore($id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function persistedAttributes(): array
    {
        return [
            'name'      => (string) $this->input('name'),
            'is_active' => (bool) $this->boolean('is_active'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name'      => __('expense_categories.fields.name'),
            'is_active' => __('expense_categories.fields.is_active'),
        ];
    }
}
