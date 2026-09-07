<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the base-currency settings form.
 *
 *   - `base_currency_code` must be a known, active ISO currency.
 *   - `symbol` / placement / decimals / separators are the editable
 *     display format, written back onto that currency's row.
 *
 * Separators are capped at 2 chars (covers ' ', ',', '.', '’', and the
 * non-breaking/thin spaces some locales use). `thousands_separator` may
 * be blank (no grouping); `decimal_separator` is required whenever the
 * currency shows decimals.
 */
class CurrencySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'base_currency_code' => [
                'required', 'string', 'size:3',
                Rule::exists('currencies', 'code')->where('is_active', true),
            ],
            'symbol'              => ['required', 'string', 'max:8'],
            'symbol_first'        => ['required', 'boolean'],
            'decimals'            => ['required', 'integer', 'between:0,4'],
            'thousands_separator' => ['nullable', 'string', 'max:2'],
            'decimal_separator'   => ['nullable', 'string', 'max:2', 'required_unless:decimals,0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'base_currency_code'  => 'base currency',
            'symbol_first'        => 'symbol placement',
            'thousands_separator' => 'thousands separator',
            'decimal_separator'   => 'decimal separator',
        ];
    }

    /**
     * Normalized format attributes to write onto the currencies row.
     *
     * @return array<string, mixed>
     */
    public function formatAttributes(): array
    {
        return [
            'symbol'              => (string) $this->input('symbol'),
            'symbol_first'        => $this->boolean('symbol_first'),
            'decimals'            => (int) $this->input('decimals'),
            // Empty string is a valid "no grouping" thousands separator.
            'thousands_separator' => (string) ($this->input('thousands_separator') ?? ''),
            'decimal_separator'   => (string) ($this->input('decimal_separator') ?: '.'),
        ];
    }
}
