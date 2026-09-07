<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PATCH /admin/settings/email.
 */
class EmailSettingsRequest extends FormRequest
{
    public const DRIVERS     = ['smtp', 'log'];
    public const ENCRYPTIONS = ['tls', 'ssl'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mail_driver'        => ['nullable', Rule::in(self::DRIVERS)],
            'mail_host'          => ['nullable', 'string', 'max:128'],
            'mail_port'          => ['nullable', 'integer', 'between:1,65535'],
            'mail_username'      => ['nullable', 'string', 'max:191'],
            'mail_password'      => ['nullable', 'string', 'max:191'],
            'mail_encryption'    => ['nullable', Rule::in(self::ENCRYPTIONS)],
            'mail_from_address'  => ['nullable', 'email:rfc', 'max:191'],
            'mail_from_name'     => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * Payload to persist. A blank password keeps whatever's already saved
     * — without this, simply re-saving the form would wipe the password.
     *
     * @return array<string, mixed>
     */
    public function mailData(?string $currentPassword): array
    {
        $data = [
            'mail_driver'       => $this->input('mail_driver') ?: null,
            'mail_host'         => $this->input('mail_host') ?: null,
            'mail_port'         => $this->filled('mail_port') ? (int) $this->input('mail_port') : null,
            'mail_username'     => $this->input('mail_username') ?: null,
            'mail_encryption'   => $this->input('mail_encryption') ?: null,
            'mail_from_address' => $this->input('mail_from_address') ?: null,
            'mail_from_name'    => $this->input('mail_from_name') ?: null,
        ];

        $submitted = $this->input('mail_password');
        if ($submitted !== null && $submitted !== '') {
            $data['mail_password'] = $submitted;
        } else {
            $data['mail_password'] = $currentPassword;
        }

        return $data;
    }
}
