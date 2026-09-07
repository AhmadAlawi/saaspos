<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShiftVarianceReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\ShiftVarianceReason::class) ?? false;
    }

    /** @return array<string, mixed> */
    /**
     * Default a blank sort order to 0 BEFORE validation so the uniqueness
     * check below sees the value that will actually be stored — otherwise
     * two blank rows would each validate (null skips `unique`) and then
     * both persist as 0.
     */
    protected function prepareForValidation(): void
    {
        $raw = $this->input('sort_order');
        $this->merge([
            'sort_order' => ($raw === null || $raw === '') ? 0 : (int) $raw,
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('shiftVarianceReason')?->id;

        return [
            'code'       => [
                'required', 'string', 'max:32',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('shift_variance_reasons', 'code')->ignore($id),
            ],
            'name'       => [
                'required', 'string', 'max:100',
                Rule::unique('shift_variance_reasons', 'name')->ignore($id),
            ],
            // Sort order must be unique — two reasons can't share a position.
            'sort_order' => [
                'required', 'integer', 'min:0', 'max:9999',
                Rule::unique('shift_variance_reasons', 'sort_order')->ignore($id),
            ],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function persistedAttributes(): array
    {
        return [
            'code'       => (string) $this->input('code'),
            'name'       => (string) $this->input('name'),
            'sort_order' => (int) $this->input('sort_order', 0),
            'is_active'  => (bool) $this->boolean('is_active'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code'       => __('shift_variance_reasons.fields.code'),
            'name'       => __('shift_variance_reasons.fields.name'),
            'sort_order' => __('shift_variance_reasons.fields.sort_order'),
            'is_active'  => __('shift_variance_reasons.fields.is_active'),
        ];
    }
}
