<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PATCH /admin/settings/updates — the channel + auto-check
 * preferences on the Updates page.
 */
class UpdateSettingsRequest extends FormRequest
{
    public const CHANNELS = ['stable', 'beta'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('updater.check');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'update_channel'      => ['required', Rule::in(self::CHANNELS)],
            'update_auto_check'   => ['sometimes', 'boolean'],
            'update_auto_install' => ['sometimes', 'boolean'],
            'update_pinned'       => ['sometimes', 'boolean'],
        ];
    }
}
