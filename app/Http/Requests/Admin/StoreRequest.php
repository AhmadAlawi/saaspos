<?php

namespace App\Http\Requests\Admin;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates POST /admin/stores (create) and PATCH /admin/stores/{store}.
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $store = $this->route('store');
        $id    = $store instanceof Store ? $store->id : null;

        return [
            'code' => [
                'required', 'string', 'max:32', 'alpha_dash',
                Rule::unique('stores', 'code')->whereNull('deleted_at')->ignore($id),
            ],
            'name'          => ['required', 'string', 'max:191'],
            'address_line1' => ['nullable', 'string', 'max:191'],
            'address_line2' => ['nullable', 'string', 'max:191'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'country_code'  => ['nullable', 'string', 'size:2'],
            'phone'         => ['nullable', 'string', 'max:32'],
            'email'         => ['nullable', 'email', 'max:191'],
            // Localization (currency / time zone / locale / rounding) is
            // system-wide, not per-store — the controller fills those in.
            'enforce_shifts'             => ['sometimes', 'boolean'],
            'require_day_open'           => ['sometimes', 'boolean'],
            'discount_threshold_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_inclusive_pricing'      => ['sometimes', 'boolean'],
            'is_active'             => ['sometimes', 'boolean'],
            'is_default'            => ['sometimes', 'boolean'],
            'receipt_template_id'   => ['nullable', 'integer', 'exists:receipt_templates,id'],
        ];
    }

    /** @return array<string, mixed> */
    public function persistedAttributes(): array
    {
        $data = $this->validated();

        $data['code']         = Str::upper($data['code']);
        $data['country_code'] = ! empty($data['country_code']) ? Str::upper($data['country_code']) : null;

        $data['enforce_shifts']        = $this->boolean('enforce_shifts');
        $data['require_day_open']      = $this->boolean('require_day_open');
        $data['discount_threshold_percent'] = ($this->input('discount_threshold_percent') === null || $this->input('discount_threshold_percent') === '')
            ? 10
            : $this->input('discount_threshold_percent');
        $data['tax_inclusive_pricing'] = $this->boolean('tax_inclusive_pricing');
        $data['is_active']             = $this->boolean('is_active', true);

        // `is_default` is never mass-assigned — the singleton invariant is
        // enforced by SetDefaultStore, which the controller calls separately.
        unset($data['is_default']);

        $data['receipt_template_id'] = ($this->input('receipt_template_id') ?: null);

        return $data;
    }
}
