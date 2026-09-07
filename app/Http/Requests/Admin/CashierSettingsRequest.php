<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PATCH /admin/settings/cashier.
 *
 * Shape mirrors {@see \App\Models\Company::cashier()} — every key here
 * is a knob the cashier UI consumes. Adding a knob: extend rules + the
 * `cashierData()` exporter below, then bump the default in the model
 * accessor and the form.
 */
class CashierSettingsRequest extends FormRequest
{
    public const LAYOUTS    = ['beam', 'lane', 'counter', 'focus'];
    public const TILE_SIZES = ['compact', 'comfortable', 'spacious'];
    public const THEMES     = ['auto', 'light', 'dark'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'layout'           => ['required', Rule::in(self::LAYOUTS)],
            'tile_size'        => ['required', Rule::in(self::TILE_SIZES)],
            'theme_default'    => ['required', Rule::in(self::THEMES)],
            'show_tax_line'    => ['sometimes', 'boolean'],
            'show_from_prefix' => ['sometimes', 'boolean'],
            'show_quick_picks' => ['sometimes', 'boolean'],
            'sound_on_add'     => ['sometimes', 'boolean'],
            'default_category' => ['nullable', 'string', 'max:32'],
            'allow_negative_stock' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The exact shape we persist into `company.cashier_settings`.
     *
     * @return array<string, mixed>
     */
    public function cashierData(): array
    {
        return [
            'layout'           => (string) $this->input('layout'),
            'tile_size'        => (string) $this->input('tile_size'),
            'theme_default'    => (string) $this->input('theme_default'),
            'show_tax_line'    => $this->boolean('show_tax_line'),
            'show_from_prefix' => $this->boolean('show_from_prefix'),
            'show_quick_picks' => $this->boolean('show_quick_picks'),
            'sound_on_add'     => $this->boolean('sound_on_add'),
            'default_category' => trim((string) $this->input('default_category', 'all')) ?: 'all',
            'allow_negative_stock' => $this->boolean('allow_negative_stock'),
        ];
    }
}
