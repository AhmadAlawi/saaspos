<?php

namespace App\Http\Requests\Admin;

use App\Models\TaxGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /admin/tax-groups (create) and PATCH /admin/tax-groups/{id}.
 *
 * Components participating in this group come in as `component_ids[]` —
 * the action layer attaches the pivot rows in that array order so the
 * resolver iterates them deterministically (matters for India GST
 * receipts that print CGST before SGST).
 *
 * `classification` taxonomy mirrors §5.2 of the feature doc:
 *   taxable / nil_rated / zero_rated / exempt / composition / reverse_charge
 */
class TaxGroupRequest extends FormRequest
{
    public const CLASSIFICATIONS = ['taxable', 'nil_rated', 'zero_rated', 'exempt', 'composition', 'reverse_charge'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge([
                'code' => mb_strtoupper(trim(preg_replace('/\s+/', '_', (string) $this->input('code')))),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $row = $this->route('taxGroup');
        $id  = $row instanceof TaxGroup ? $row->id : null;

        return [
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('tax_groups', 'code')->ignore($id),
            ],
            'name'              => ['required', 'string', 'max:100'],
            'classification'    => ['required', 'string', Rule::exists('tax_classifications', 'slug')->where('is_active', true)],
            'is_inclusive'      => ['sometimes', 'boolean'],
            'is_default'        => ['sometimes', 'boolean'],
            'is_active'         => ['sometimes', 'boolean'],

            // Component picker. Empty for exempt / zero / nil — the action
            // is fine with that. Taxable groups with no components total
            // to 0% but stay valid (lets the user save in progress).
            'component_ids'     => ['nullable', 'array', 'max:20'],
            'component_ids.*'   => ['integer', Rule::exists('tax_components', 'id')],
        ];
    }

    /** @return array<string, mixed> */
    public function persistedAttributes(): array
    {
        $data = $this->validated();
        $data['is_inclusive']  = $this->boolean('is_inclusive');
        $data['is_default']    = $this->boolean('is_default');
        $data['is_active']     = $this->boolean('is_active', true);
        unset($data['component_ids']);
        return $data;
    }

    /** @return list<int> */
    public function componentIds(): array
    {
        return array_values(array_map('intval', $this->input('component_ids', []) ?? []));
    }
}
