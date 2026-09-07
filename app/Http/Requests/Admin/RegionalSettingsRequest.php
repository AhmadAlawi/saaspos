<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PATCH /admin/settings/regional. Date/time formats are
 * constrained to a preset list so we never store an arbitrary format
 * string.
 */
class RegionalSettingsRequest extends FormRequest
{
    /** Preset PHP date() format strings offered in the picker. */
    public const DATE_FORMATS = [
        'd M Y',        // 31 Jan 2026
        'd-m-Y',        // 31-01-2026
        'd/m/Y',        // 31/01/2026
        'd.m.Y',        // 31.01.2026
        'm/d/Y',        // 01/31/2026
        'm-d-Y',        // 01-31-2026
        'Y-m-d',        // 2026-01-31
        'Y/m/d',        // 2026/01/31
        'M d, Y',       // Jan 31, 2026
        'F j, Y',       // January 31, 2026
        'jS M Y',       // 31st Jan 2026
        'd M, Y',       // 31 Jan, 2026
        'D, d M Y',     // Sat, 31 Jan 2026
        'l, d F Y',     // Saturday, 31 January 2026
    ];

    /** Preset PHP date() time format strings offered in the picker. */
    public const TIME_FORMATS = [
        'h:i A',        // 02:05 PM
        'h:i a',        // 02:05 pm
        'g:i A',        // 2:05 PM
        'g:i a',        // 2:05 pm
        'H:i',          // 14:05
        'h:i:s A',      // 02:05:09 PM
        'H:i:s',        // 14:05:09
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'timezone'                 => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'date_format'              => ['required', Rule::in(self::DATE_FORMATS)],
            'time_format'              => ['required', Rule::in(self::TIME_FORMATS)],
            // Month the accounting/financial year begins (1 = Jan … 12 = Dec).
            'fiscal_year_start_month'  => ['sometimes', 'integer', 'between:1,12'],
        ];
    }

    /** @return array<string, mixed> */
    public function regionalData(): array
    {
        return $this->only(['timezone', 'date_format', 'time_format', 'fiscal_year_start_month']);
    }
}
