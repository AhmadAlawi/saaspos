<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /admin/settings/restore/validate — the wizard's "review"
 * step. The source is EITHER an existing local backup (`backup_id`) OR an
 * uploaded `.zip` (`file`); exactly one must be present.
 */
class ValidateBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('backup.restore');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'backup_id' => ['nullable', 'integer'],
            // 2 GB ceiling (in KB). PHP's upload_max_filesize / post_max_size
            // will usually cap well below this on shared hosting.
            'file'      => ['nullable', 'file', 'mimes:zip', 'max:2097152'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('backup_id') && ! $this->hasFile('file')) {
                $validator->errors()->add('file', __('restore.errors.no_source'));
            }
        });
    }
}
