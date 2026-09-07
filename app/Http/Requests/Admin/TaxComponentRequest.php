<?php

namespace App\Http\Requests\Admin;

use App\Models\TaxComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /admin/tax-components (create) and PATCH /admin/tax-components/{id}.
 *
 * Slice 1 keeps the field set minimal — code, name, rate, is_active.
 * Recoverable / reverse-chargeable / accounting account columns ship
 * to the table from the migration but their UI lands with Slice 2/3
 * (when exemptions and reverse charge come online).
 */
class TaxComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Codes are uppercase identifiers — same convention the seeded
        // rows use (IN_CGST_9, UK_VAT_20). Normalize before unique check
        // so "in_cgst_9" and "IN_CGST_9" don't both insert.
        if ($this->filled('code')) {
            $this->merge([
                'code' => mb_strtoupper(trim(preg_replace('/\s+/', '_', (string) $this->input('code')))),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $row = $this->route('taxComponent');
        $id  = $row instanceof TaxComponent ? $row->id : null;

        return [
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('tax_components', 'code')->ignore($id),
            ],
            'name'      => ['required', 'string', 'max:100', Rule::unique('tax_components', 'name')->ignore($id)],
            // 0%-100%. Components above 100% are nonsensical; if a
            // jurisdiction ever asks for >100% it's actually a compound
            // tax modelled as multiple components, not one big one.
            'rate'      => ['required', 'numeric', 'between:0,100'],
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
}
