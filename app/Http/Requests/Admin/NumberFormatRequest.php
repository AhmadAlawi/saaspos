<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PATCH /admin/settings/numbering. Each format string MUST
 * contain a `{seq}` or `{seq:N}` placeholder — without it the generator
 * would produce duplicate numbers and trip the unique constraint on
 * `(store_id, number)`.
 */
class NumberFormatRequest extends FormRequest
{
    public const MAX_LEN = 191;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sale_number_format' => ['nullable', 'string', 'max:'.self::MAX_LEN, 'regex:/\{seq(?::\d+)?\}/'],
            'hold_number_format' => ['nullable', 'string', 'max:'.self::MAX_LEN, 'regex:/\{seq(?::\d+)?\}/'],
        ];
    }

    public function messages(): array
    {
        return [
            'sale_number_format.regex' => 'Sale number format must include {seq} or {seq:N}.',
            'hold_number_format.regex' => 'Hold number format must include {seq} or {seq:N}.',
        ];
    }

    /** @return array<string, mixed> */
    public function numberFormatData(): array
    {
        return [
            'sale_number_format' => trim((string) $this->input('sale_number_format')) ?: null,
            'hold_number_format' => trim((string) $this->input('hold_number_format')) ?: null,
        ];
    }
}
