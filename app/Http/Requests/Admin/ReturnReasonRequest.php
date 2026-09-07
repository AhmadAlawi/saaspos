<?php

namespace App\Http\Requests\Admin;

use App\Models\ReturnReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the return-reason create / update side editor.
 *
 * `code` is the stable machine key (UNIQUE across active + deleted —
 * no soft delete on this table). `default_restock` is honoured by the
 * refund form when the cashier picks the reason; `requires_permission`
 * is reserved for a future "manager override" gate (currently unused
 * by the action layer).
 */
class ReturnReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $row = $this->route('returnReason');
        $id  = $row instanceof ReturnReason ? $row->id : null;

        return [
            'code'                => ['required', 'string', 'max:32', Rule::unique('return_reasons', 'code')->ignore($id)],
            'name'                => ['required', 'string', 'max:100'],
            'sort_order'          => ['nullable', 'integer', 'between:0,9999'],
            'default_restock'     => ['sometimes', 'boolean'],
            'requires_permission' => ['sometimes', 'boolean'],
            'is_active'           => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function persistedAttributes(): array
    {
        return [
            'code'                => trim((string) $this->input('code')),
            'name'                => trim((string) $this->input('name')),
            'sort_order'          => (int) ($this->input('sort_order') ?: 0),
            'default_restock'     => $this->boolean('default_restock'),
            'requires_permission' => $this->boolean('requires_permission'),
            'is_active'           => $this->boolean('is_active', true),
        ];
    }
}
