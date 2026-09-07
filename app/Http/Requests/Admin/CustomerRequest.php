<?php

namespace App\Http\Requests\Admin;

use App\Models\Customer;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the customer create / edit form. Phone, WhatsApp number, and
 * email are each unique across customers (ignoring soft-deleted rows and
 * the customer being edited). The `code` is unique when present; the
 * action layer auto-generates it when blank.
 *
 * Phone/WhatsApp are normalised in {@see prepareForValidation()} so the
 * uniqueness check compares the same digits-with-optional-`+` form the
 * action stores; email is lower-cased for the same reason.
 *
 * Address rows arrive as an array under `addresses[]`. Empty rows are
 * dropped by the action; we only validate non-empty ones here.
 */
class CustomerRequest extends FormRequest
{
    public const GENDERS = ['male', 'female', 'other', 'prefer_not_to_say'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise phone/whatsapp/email BEFORE validation so the uniqueness
     * rules compare the stored form, not the raw user input.
     */
    protected function prepareForValidation(): void
    {
        $email = trim((string) $this->input('email', ''));

        $this->merge([
            'phone'          => PhoneNormalizer::normalize($this->input('phone')),
            'whatsapp_phone' => PhoneNormalizer::normalize($this->input('whatsapp_phone')),
            'email'          => $email === '' ? null : strtolower($email),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $row = $this->route('customer');
        $id  = $row instanceof Customer ? $row->id : null;

        return [
            // Identity
            'code'           => ['nullable', 'string', 'max:32', Rule::unique('customers', 'code')->whereNull('deleted_at')->ignore($id)],
            'name'           => ['required', 'string', 'max:191'],
            'phone'          => ['nullable', 'string', 'max:32', Rule::unique('customers', 'phone')->whereNull('deleted_at')->ignore($id)],
            'whatsapp_phone' => ['nullable', 'string', 'max:32', Rule::unique('customers', 'whatsapp_phone')->whereNull('deleted_at')->ignore($id)],
            'whatsapp_opt_out' => ['sometimes', 'boolean'],
            'email'          => ['nullable', 'email:rfc', 'max:191', Rule::unique('customers', 'email')->whereNull('deleted_at')->ignore($id)],
            'dob'            => ['nullable', 'date', 'before:today'],
            'gender'         => ['nullable', Rule::in(self::GENDERS)],

            // B2B
            'is_business'             => ['sometimes', 'boolean'],
            'business_name'           => ['nullable', 'string', 'max:191', 'required_if:is_business,1'],
            'gstin'                   => ['nullable', 'string', 'max:32'],
            'pan'                     => ['nullable', 'string', 'max:16'],
            'tax_registration_number' => ['nullable', 'string', 'max:64'],

            // Pricing / loyalty
            'customer_group_id'        => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('is_active', true)],
            'default_discount_percent' => ['nullable', 'numeric', 'between:0,100'],

            // Credit (only credit_limit is editable here; balances move via dedicated actions)
            'credit_limit'             => ['nullable', 'numeric', 'min:0'],

            // Internal
            'notes'     => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],

            // Addresses (array of rows)
            'addresses'                  => ['sometimes', 'array', 'max:20'],
            'addresses.*.label'          => ['nullable', 'string', 'max:32'],
            'addresses.*.line1'          => ['nullable', 'string', 'max:191'],
            'addresses.*.line2'          => ['nullable', 'string', 'max:191'],
            'addresses.*.city'           => ['nullable', 'string', 'max:100'],
            'addresses.*.state'          => ['nullable', 'string', 'max:100'],
            'addresses.*.postal_code'    => ['nullable', 'string', 'max:20'],
            'addresses.*.country_code'   => ['nullable', 'string', 'size:2'],
            'addresses.*.landmark'       => ['nullable', 'string', 'max:191'],
            'addresses.*.is_default'     => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'business_name.required_if' => __('customers.validation.business_name_required'),
            'phone.unique'              => __('customers.validation.phone_unique'),
            'whatsapp_phone.unique'     => __('customers.validation.whatsapp_unique'),
            'email.unique'              => __('customers.validation.email_unique'),
        ];
    }

    /** @return array<string, mixed> */
    public function customerData(): array
    {
        $data = $this->safe()->except('addresses');
        $data['is_business']      = $this->boolean('is_business');
        $data['is_active']        = $this->boolean('is_active', true);
        $data['whatsapp_opt_out'] = $this->boolean('whatsapp_opt_out');

        // Not a business → clear every B2B field, so unchecking the toggle
        // resets the GSTIN / PAN / tax details server-side regardless of
        // what the (now-hidden) inputs submitted.
        if (! $data['is_business']) {
            foreach (['business_name', 'gstin', 'pan', 'tax_registration_number'] as $k) {
                $data[$k] = null;
            }
        }

        // Empty strings → null for cleaner storage.
        foreach (['code', 'phone', 'whatsapp_phone', 'email', 'dob', 'gender',
                  'business_name', 'gstin', 'pan', 'tax_registration_number', 'notes'] as $k) {
            if (isset($data[$k]) && $data[$k] === '') {
                $data[$k] = null;
            }
        }

        // Numeric blanks → null so the column accepts them.
        foreach (['customer_group_id', 'default_discount_percent', 'credit_limit'] as $k) {
            if (! isset($data[$k]) || $data[$k] === '') {
                $data[$k] = null;
            }
        }

        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    public function addressData(): array
    {
        return array_values($this->input('addresses', []) ?: []);
    }
}
