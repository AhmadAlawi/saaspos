<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /admin/settings/updates/manual/upload — the manual update
 * package the admin uploads. Same dangerous gate as a feed install.
 */
class ManualUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('updater.run');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // 2 GB ceiling (KB); PHP upload limits usually cap lower on shared hosting.
            'file' => ['required', 'file', 'mimes:zip', 'max:2097152'],
        ];
    }
}
