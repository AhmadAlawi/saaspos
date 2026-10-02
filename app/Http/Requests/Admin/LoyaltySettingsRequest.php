<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LoyaltySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->hasPermission('settings.update'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'loyalty_enabled'     => ['nullable', 'boolean'],
            'loyalty_earn_rate'   => ['required', 'numeric', 'min:0', 'max:9999'],
            'loyalty_redeem_rate' => ['required', 'numeric', 'min:0.0001', 'max:999999'],
        ];
    }

    /** @return array<string, mixed> */
    public function loyaltyData(): array
    {
        return [
            'loyalty_enabled'     => $this->boolean('loyalty_enabled'),
            'loyalty_earn_rate'   => $this->input('loyalty_earn_rate'),
            'loyalty_redeem_rate' => $this->input('loyalty_redeem_rate'),
        ];
    }
}
