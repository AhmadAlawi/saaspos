<?php

namespace App\Http\Requests\Cashier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the activity-log POST fired by the cashier screen after any
 * tracked action (cart edit, discount, hold/void, drawer kick, shift
 * open/close, print, checkout, refund, client-side JS error).
 * Server-trusted fields (store, terminal, user, shift) are filled in the
 * action from the session — the client only reports WHAT happened, not
 * WHO/WHERE, so a compromised or buggy client can't forge attribution.
 */
class RecordCashierActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type'           => ['required', Rule::in([
                'cart', 'discount', 'sale', 'refund', 'drawer', 'shift', 'print', 'auth', 'error',
            ])],
            'action'         => ['required', 'string', 'max:64'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id'   => ['nullable', 'integer'],
            'meta'           => ['nullable', 'array'],
        ];
    }
}
