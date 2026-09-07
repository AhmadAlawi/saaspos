<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PATCH /admin/settings/scale.
 *
 * Shape mirrors {@see \App\Models\Company::scale()} — the weighing-scale
 * barcode template the cashier decodes. Adding a knob: extend rules + the
 * `scaleData()` exporter, then bump the default in the model accessor, the
 * cashier factory defaults, and the form.
 */
class ScaleSettingsRequest extends FormRequest
{
    public const EMBED_TYPES = ['weight', 'price'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled'        => ['sometimes', 'boolean'],
            'prefix'         => ['required', 'string', 'regex:/^[0-9]{1,3}$/'],
            'plu_length'     => ['required', 'integer', 'min:1', 'max:8'],
            'value_offset'   => ['required', 'integer', 'min:0', 'max:4'],
            'value_length'   => ['required', 'integer', 'min:1', 'max:8'],
            'value_decimals' => ['required', 'integer', 'min:0', 'max:4'],
            'embed_type'     => ['required', Rule::in(self::EMBED_TYPES)],
        ];
    }

    /**
     * The exact shape we persist into `company.scale_settings`.
     *
     * @return array<string, mixed>
     */
    public function scaleData(): array
    {
        return [
            'enabled'        => $this->boolean('enabled'),
            'prefix'         => (string) $this->input('prefix', '2'),
            'plu_length'     => (int) $this->input('plu_length', 5),
            'value_offset'   => (int) $this->input('value_offset', 0),
            'value_length'   => (int) $this->input('value_length', 5),
            'value_decimals' => (int) $this->input('value_decimals', 3),
            'embed_type'     => $this->input('embed_type') === 'price' ? 'price' : 'weight',
        ];
    }
}
