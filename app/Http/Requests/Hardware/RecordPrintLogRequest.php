<?php

namespace App\Http\Requests\Hardware;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the print-log POST sent by the client bridge after a print
 * attempt. Server-trusted fields (store, terminal, user) are filled in
 * the action from the session — the client only reports what it did.
 */
class RecordPrintLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reference_type' => ['required', 'string', Rule::in(['Sale', 'SaleReturn', 'Shift', 'Label', 'TradingDay'])],
            'reference_id'   => ['nullable', 'integer'],
            'printer_type'   => ['required', Rule::in(['receipt', 'label'])],
            'mode'           => ['required', Rule::in(['webusb', 'browser_print', 'network'])],
            'status'         => ['required', Rule::in(['success', 'failed', 'queued'])],
            'error_message'  => ['nullable', 'string', 'max:1000'],
            'bytes_size'     => ['nullable', 'integer', 'min:0'],
        ];
    }
}
