<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the opening-balances post. `balances` is a map of account_id =>
 * natural-side amount; blank / zero entries are ignored downstream. The
 * "editable until first transaction" guard is enforced in the controller.
 */
class OpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'store_id'   => ['nullable', 'integer', Rule::exists('stores', 'id')],
            'balances'   => ['array'],
            'balances.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
