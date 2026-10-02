<?php

namespace App\Http\Requests\Cashier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

/**
 * Validates POST cashier/pin/change — a user changing their OWN PIN.
 * `current_pin` is checked by hand against `Auth::user()->pin` (not the
 * `current_password` rule, which only ever checks the `password`
 * column) inside `withValidator()` so a wrong PIN fails validation the
 * same uniform way every other field does.
 */
class ChangePinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_pin' => ['required', 'digits:6'],
            'pin'         => ['required', 'digits:6', 'confirmed', 'different:current_pin'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();
            if (! $user?->pin) {
                $validator->errors()->add('current_pin', __('account.pin.not_set'));
                return;
            }
            if (! Hash::check((string) $this->input('current_pin'), $user->pin)) {
                $validator->errors()->add('current_pin', __('account.pin.incorrect'));
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'current_pin' => __('account.pin.current'),
            'pin'         => __('account.pin.new'),
        ];
    }
}
