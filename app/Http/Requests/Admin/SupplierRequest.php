<?php

namespace App\Http\Requests\Admin;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the supplier create / edit form. Phone + email are not
 * unique by default (per feature doc §3.3 — duplicate detection is
 * surfaced inline). The `code` is unique among live rows; the action
 * layer auto-generates it when blank.
 *
 * Outstanding balance is not editable here — only the
 * supplier-payment / purchase-receipt actions touch it.
 */
class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $row = $this->route('supplier');
        $id  = $row instanceof Supplier ? $row->id : null;

        return [
            // Identity
            'code'           => ['nullable', 'string', 'max:32', Rule::unique('suppliers', 'code')->whereNull('deleted_at')->ignore($id)],
            'name'           => ['required', 'string', 'max:191'],
            'business_name'  => ['nullable', 'string', 'max:191'],
            'contact_person' => ['nullable', 'string', 'max:191'],
            'phone'          => ['nullable', 'string', 'max:32'],
            'email'          => ['nullable', 'email:rfc', 'max:191'],

            // Tax & registration
            'gstin'                   => ['nullable', 'string', 'max:64'],
            'pan'                     => ['nullable', 'string', 'max:16'],
            'tax_registration_number' => ['nullable', 'string', 'max:64'],

            // Terms
            'default_currency_code' => ['nullable', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'payment_terms_days'    => ['nullable', 'integer', 'between:0,365'],

            // Primary address
            'address_line1' => ['nullable', 'string', 'max:191'],
            'address_line2' => ['nullable', 'string', 'max:191'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'country_code'  => ['nullable', 'string', 'size:2'],

            // Internal
            'notes'     => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function supplierData(): array
    {
        $data = $this->safe()->all();
        $data['is_active'] = $this->boolean('is_active', true);

        // Empty strings → null for cleaner storage.
        foreach ([
            'code', 'business_name', 'contact_person', 'phone', 'email',
            'gstin', 'pan', 'tax_registration_number',
            'default_currency_code',
            'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country_code',
            'notes',
        ] as $k) {
            if (isset($data[$k]) && $data[$k] === '') {
                $data[$k] = null;
            }
        }

        if (! isset($data['payment_terms_days']) || $data['payment_terms_days'] === '') {
            $data['payment_terms_days'] = null;
        }

        if (isset($data['country_code'])) {
            $data['country_code'] = $data['country_code'] !== null
                ? strtoupper(substr((string) $data['country_code'], 0, 2))
                : null;
        }
        if (isset($data['default_currency_code'])) {
            $data['default_currency_code'] = $data['default_currency_code'] !== null
                ? strtoupper((string) $data['default_currency_code'])
                : null;
        }

        return $data;
    }
}
