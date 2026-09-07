<?php

namespace App\Http\Requests\Admin;

use App\Models\AccountGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates chart-of-accounts group create / update. A new group always needs
 * a parent (no new roots); on update the parent is optional (rename-only).
 * Reparent constraints (same type, no cycles, system groups fixed) live in
 * {@see \App\Actions\Accounting\UpdateAccountGroup}.
 */
class AccountGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isUpdate = $this->route('group') instanceof AccountGroup;

        return [
            'name'      => ['required', 'string', 'max:100'],
            'parent_id' => [
                $isUpdate ? 'nullable' : 'required',
                'integer',
                Rule::exists('account_groups', 'id'),
            ],
        ];
    }
}
