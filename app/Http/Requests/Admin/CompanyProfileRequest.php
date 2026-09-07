<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validates PATCH /admin/settings/company.
 */
class CompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'max:191'],
            'legal_name'              => ['nullable', 'string', 'max:191'],
            'tax_registration_number' => ['nullable', 'string', 'max:64'],
            'email'                   => ['nullable', 'email', 'max:191'],
            'phone'                   => ['nullable', 'string', 'max:32'],
            'website'                 => ['nullable', 'url', 'max:191'],
            'address_line1'           => ['nullable', 'string', 'max:191'],
            'address_line2'           => ['nullable', 'string', 'max:191'],
            'city'                    => ['nullable', 'string', 'max:100'],
            'state'                   => ['nullable', 'string', 'max:100'],
            'postal_code'             => ['nullable', 'string', 'max:20'],
            'country_code'            => ['nullable', 'string', 'size:2'],
            'logo'                    => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:1024'],
            'logo_remove'             => ['sometimes', 'boolean'],
        ];
    }

    /** Profile fields to persist (logo handled separately by the controller). */
    public function profileData(): array
    {
        $data = $this->only([
            'name', 'legal_name', 'tax_registration_number', 'email', 'phone', 'website',
            'address_line1', 'address_line2', 'city', 'state', 'postal_code',
        ]);

        $data['country_code'] = $this->filled('country_code') ? Str::upper($this->input('country_code')) : null;

        return $data;
    }
}
