<?php

namespace App\Http\Requests\Admin;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PATCH /admin/profile — a user editing their OWN profile +
 * preferences. Self-service: any authenticated user may edit themselves,
 * so there's no permission gate.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:191'],
            'email'            => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone'            => ['nullable', 'string', 'max:32'],
            'locale'           => ['nullable', 'string', 'max:8'],
            'default_store_id' => ['nullable', 'integer'],
            'avatar'           => ['nullable', 'image', 'max:8192'],
            'avatar_remove'    => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $storeId = $this->input('default_store_id');
            if ($storeId && ! $this->user()->canAccessStore((int) $storeId)) {
                $validator->errors()->add('default_store_id', __('account.errors.store_not_accessible'));
            }

            $locale = $this->input('locale');
            if ($locale && ! Language::query()->active()->where('code', $locale)->exists()) {
                $validator->errors()->add('locale', __('account.errors.locale_invalid'));
            }
        });
    }

    /** @return array<string, mixed> */
    public function profileData(): array
    {
        return [
            'name'             => $this->input('name'),
            'email'            => $this->input('email'),
            'phone'            => $this->input('phone') ?: null,
            'locale'           => $this->input('locale') ?: null,
            'default_store_id' => $this->input('default_store_id') ?: null,
        ];
    }
}
