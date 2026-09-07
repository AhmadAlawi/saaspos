<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PricingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->hasPermission('settings.update'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'auto_apply_markup_on_receive' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function pricingData(): array
    {
        return [
            'auto_apply_markup_on_receive' => $this->boolean('auto_apply_markup_on_receive'),
        ];
    }
}
