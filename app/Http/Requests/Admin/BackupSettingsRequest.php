<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PATCH /admin/settings/backup.
 */
class BackupSettingsRequest extends FormRequest
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    /**
     * Disks the backup module is wired up to write to. Add new ones here
     * as they get implemented — S3 is on the roadmap but not built yet,
     * so it's intentionally absent.
     */
    public const ALLOWED_DISKS = ['local'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'backup_enabled'         => ['sometimes', 'boolean'],
            'backup_frequency'       => ['required', Rule::in(self::FREQUENCIES)],
            'backup_retention_days'  => ['required', 'integer', 'between:1,3650'],
            'backup_target_disk'     => ['required', Rule::in(self::ALLOWED_DISKS)],
            'backup_include_uploads' => ['sometimes', 'boolean'],
            'backup_email_enabled'   => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function backupData(): array
    {
        return [
            'backup_enabled'         => $this->boolean('backup_enabled'),
            'backup_frequency'       => $this->input('backup_frequency'),
            'backup_retention_days'  => (int) $this->input('backup_retention_days'),
            'backup_target_disk'     => $this->input('backup_target_disk'),
            'backup_include_uploads' => $this->boolean('backup_include_uploads'),
            'backup_email_enabled'   => $this->boolean('backup_email_enabled'),
        ];
    }
}
