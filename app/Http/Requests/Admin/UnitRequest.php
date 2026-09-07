<?php

namespace App\Http\Requests\Admin;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /admin/units (create) and PATCH /admin/units/{id}.
 *
 * Cross-field rules enforced here:
 *   - `code` is unique among non-trashed rows
 *   - a derived unit (base_unit_id set) MUST carry a conversion_factor
 *   - base_unit_id cannot point at the row being edited (self-loop)
 */
class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $unit = $this->route('unit');
        $id   = $unit instanceof Unit ? $unit->id : null;

        return [
            'code' => [
                'required',
                'string',
                'max:16',
                Rule::unique('units', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'name'     => ['required', 'string', 'max:64'],
            'category' => ['required', 'string', Rule::exists('unit_categories', 'slug')->where('is_active', true)],
            'base_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->whereNull('deleted_at'),
                $unit ? $this->noSelfRule($unit) : 'nullable',
            ],
            'conversion_factor' => [
                'nullable',
                'numeric',
                'gt:0',
                Rule::requiredIf(fn () => filled($this->input('base_unit_id'))),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function persistedAttributes(): array
    {
        $data = $this->validated();
        $data['is_active'] = $this->boolean('is_active', true);
        return $data;
    }

    /** Forbid a unit pointing at itself as base. */
    private function noSelfRule(Unit $editing): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($editing) {
            if ($value === null || $value === '') return;
            if ((int) $value === $editing->id) {
                $fail(__('units.errors.base_self'));
            }
        };
    }
}
