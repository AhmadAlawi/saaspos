<?php

namespace App\Http\Requests\Admin;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /admin/languages and PATCH /admin/languages/{language}.
 */
class LanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $language = $this->route('language');
        $id       = $language instanceof Language ? $language->id : null;

        return [
            'code' => [
                'required', 'string', 'max:12', 'regex:/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})?$/',
                Rule::unique('languages', 'code')->ignore($id),
            ],
            'name'        => ['required', 'string', 'max:64'],
            'native_name' => ['nullable', 'string', 'max:64'],
            'direction'   => ['required', Rule::in(['ltr', 'rtl'])],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function languageAttributes(): array
    {
        return [
            'code'        => $this->input('code'),
            'name'        => $this->input('name'),
            'native_name' => $this->input('native_name'),
            'direction'   => $this->input('direction'),
            'is_active'   => $this->boolean('is_active', true),
        ];
    }
}
