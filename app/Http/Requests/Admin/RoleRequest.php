<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /admin/roles and PATCH /admin/roles/{role}.
 */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = $this->route('role');
        $id   = $role instanceof Role ? $role->id : null;

        return [
            'name' => [
                'required', 'string', 'max:64',
                Rule::unique('roles', 'name')->ignore($id),
            ],
            'description'   => ['nullable', 'string', 'max:191'],
            'permissions'   => ['array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ];
    }

    /** @return list<int> Selected permission ids. */
    public function permissionIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->input('permissions', []))));
    }

    /** @return array<string, mixed> */
    public function roleAttributes(): array
    {
        return [
            'name'        => (string) $this->input('name'),
            'description' => $this->input('description'),
        ];
    }
}
