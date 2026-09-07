<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('close', $this->route('shift')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'closing_cash_counted'    => ['required', 'numeric', 'min:0'],
            'variance_reason'         => ['nullable', 'string', 'max:64'],
            'variance_notes'          => ['nullable', 'string', 'max:1000'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
            // Denomination helper (Slice C) — counts per face value.
            'closing_denominations'   => ['nullable', 'array'],
            'closing_denominations.*' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'closing_cash_counted' => __('shifts.fields.closing_cash_counted'),
            'variance_reason'      => __('shifts.fields.variance_reason'),
            'variance_notes'       => __('shifts.fields.variance_notes'),
            'notes'                => __('shifts.fields.notes'),
        ];
    }
}
