<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates POST /admin/categories (create) and PATCH /admin/categories/{id}.
 *
 * Parent-id validation goes through {@see Category::wouldCycleIfParentedTo()}
 * so the cycle rule is defined once on the model and reused server-wide
 * (anywhere we accept a new parent, not just this form).
 */
class CategoryRequest extends FormRequest
{
    /** Fixed accent-color palette offered in the editor's color picker. */
    public const COLOR_CHOICES = [
        '#F97316', '#5E6AD2', '#10B981', '#EAB308',
        '#EC4899', '#06B6D4', '#8B5CF6', '#94959B',
    ];

    public function authorize(): bool
    {
        // Defers to CategoryPolicy via the controller's authorizeResource(). The
        // FormRequest itself stays permissive here so the controller-level
        // policy mapping is the single source of truth for who can do what.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $category = $this->route('category');
        $editing  = $category instanceof Category ? $category : null;

        return [
            'name'      => [
                'required', 'string', 'max:191',
                Rule::unique('categories', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($editing?->id),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->whereNull('deleted_at'),
                $editing ? $this->noCycleRule($editing) : 'nullable',
            ],
            'color'        => ['nullable', Rule::in(self::COLOR_CHOICES)],
            'tax_group_id' => [
                'nullable',
                'integer',
                Rule::exists('tax_groups', 'id')->where('is_active', true),
            ],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Map the validated payload to the fields we persist. Slug is derived
     * from the name; callers don't think about it.
     *
     * @return array<string, mixed>
     */
    public function persistedAttributes(): array
    {
        $data = $this->validated();

        $base     = Str::slug($data['name']);
        $category = $this->route('category');
        $data['slug'] = $category instanceof Category
            ? $base.'-'.$category->id
            : $base.'-'.Str::lower(Str::random(5));

        $data['is_active'] = $this->boolean('is_active', true);

        return $data;
    }

    /**
     * Closure validator that rejects a parent_id equal to the row itself
     * or one of its descendants. Both would create a cycle in the tree.
     */
    private function noCycleRule(Category $editing): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($editing) {
            if ($value === null || $value === '') return;
            $candidate = (int) $value;
            if ($candidate === $editing->id) {
                $fail(__('categories.errors.parent_self'));
                return;
            }
            if ($editing->wouldCycleIfParentedTo($candidate)) {
                $fail(__('categories.errors.parent_cycle'));
            }
        };
    }
}
