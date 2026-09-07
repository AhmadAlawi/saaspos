<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /admin/users and PATCH /admin/users/{user}.
 *
 * Password is required on create unless the admin chose the setup-link
 * flow (`password_mode = setup_link`); optional on edit (blank = keep).
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user      = $this->route('user');
        $id        = $user instanceof User ? $user->id : null;
        $isCreate  = $id === null;
        $needsPass = $isCreate && $this->input('password_mode', 'set') !== 'setup_link';

        return [
            'name'  => ['required', 'string', 'max:191'],
            'email' => [
                'required', 'email', 'max:191',
                Rule::unique('users', 'email')->whereNull('deleted_at')->ignore($id),
            ],
            'phone'    => ['nullable', 'string', 'max:32'],
            'locale'   => ['nullable', 'string', 'max:8'],
            'password' => [$needsPass ? 'required' : 'nullable', 'string', 'min:8'],
            // 6-digit PIN for the cashier's touchscreen manager-approval
            // numpad (discount/refund) — optional, blank on edit keeps it.
            'pin'      => ['nullable', 'digits:6'],
            'is_active'      => ['sometimes', 'boolean'],
            'is_super_admin' => ['sometimes', 'boolean'],
            'stores'                 => ['array', 'min:1'],
            'stores.*.store_id'      => ['required', 'integer', Rule::exists('stores', 'id')],
            'stores.*.role_id'       => ['required', 'integer', Rule::exists('roles', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'stores.min'                 => __('users.validation.stores_required'),
            'stores.*.store_id.required' => __('users.validation.store_required'),
            'stores.*.role_id.required'  => __('users.validation.role_required'),
        ];
    }

    /** @return array<string, mixed> Profile fields for the user record. */
    public function profileData(): array
    {
        return [
            'name'      => (string) $this->input('name'),
            'email'     => (string) $this->input('email'),
            'phone'     => $this->input('phone'),
            'locale'    => $this->input('locale') ?: 'en',
            'is_active' => $this->boolean('is_active', true),
        ];
    }

    /** @return array<int, int> store_id => role_id (last wins on dupes). */
    public function storeRoles(): array
    {
        $out = [];
        foreach ($this->input('stores', []) as $row) {
            if (! empty($row['store_id']) && ! empty($row['role_id'])) {
                $out[(int) $row['store_id']] = (int) $row['role_id'];
            }
        }

        return $out;
    }
}
