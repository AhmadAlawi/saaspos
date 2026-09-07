<?php

namespace App\Http\Requests\Admin;

use App\Models\StockAdjustmentReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the code BEFORE validation runs so the unique rule sees
     * the same string we'll persist — otherwise "Damaged" passes unique
     * (lowercase "damaged" doesn't match) and then we hit the DB.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge([
                'code' => strtolower(trim(preg_replace('/[\s-]+/', '_', (string) $this->input('code')))),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $row = $this->route('stockAdjustmentReason');
        $id  = $row instanceof StockAdjustmentReason ? $row->id : null;

        return [
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('stock_adjustment_reasons', 'code')->ignore($id),
            ],
            'name'       => ['required', 'string', 'max:100'],
            'is_active'  => ['sometimes', 'boolean'],
            'sort_order' => [
                'sometimes', 'integer', 'between:0,9999',
                // Sort order picks the row's position in pickers + the
                // admin list. Letting two reasons share the same number
                // makes the secondary tiebreaker (id) the actual sort
                // key, which surprises admins who reordered explicitly.
                Rule::unique('stock_adjustment_reasons', 'sort_order')->ignore($id),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sort_order.unique' => __('inventory.reasons.errors.sort_order_taken'),
        ];
    }

    /** @return array<string, mixed> */
    public function persistedAttributes(): array
    {
        $data = $this->validated();
        // Codes are slug-style identifiers (lower_snake) so they're safe
        // to embed in URLs, queries, and exported data — matching the
        // existing seeded set (physical_count, damaged, expired …).
        $data['code']       = strtolower(trim(preg_replace('/[\s-]+/', '_', $data['code'])));
        $data['is_active']  = $this->boolean('is_active', true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        return $data;
    }
}
