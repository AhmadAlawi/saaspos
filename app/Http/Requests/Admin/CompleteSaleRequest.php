<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the cashier's POST /cashier/complete payload — shape only.
 * Business rules (negative-stock, payment-sum-matches-grand-total, etc.)
 * live in {@see App\Actions\Sales\CompleteSale} per the system-wide
 * deferred-validation convention.
 */
class CompleteSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'store_id'      => ['required', 'integer', Rule::exists('stores', 'id')->where('is_active', true)],
            'customer_id'   => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'sale_date'     => ['nullable', 'date', 'before_or_equal:today'],
            'local_uuid'    => ['nullable', 'string', 'max:36'],
            'notes'         => ['nullable', 'string', 'max:5000'],
            // Signed manager-approval token for an over-threshold discount (Slice 2).
            'discount_approval' => ['nullable', 'string', 'max:2000'],
            // Order-level discount audit (Slice 3).
            'discount_type'            => ['nullable', 'string', 'in:pct,amt'],
            'discount_value'           => ['nullable', 'numeric', 'gte:0'],
            'discount_reason'          => ['nullable', 'string', 'max:255'],
            'discount_reason_category' => ['nullable', 'string', 'max:32'],

            'items'                     => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id'        => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.variant_id'        => ['nullable', 'integer'],
            'items.*.batch_id'          => ['nullable', 'integer'],
            'items.*.quantity'          => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price'        => ['required', 'numeric', 'gte:0'],
            'items.*.discount_percent'  => ['nullable', 'numeric', 'between:0,100'],
            'items.*.discount_amount'   => ['nullable', 'numeric', 'gte:0'],
            'items.*.notes'             => ['nullable', 'string', 'max:500'],

            // `present` (not `required`) so a pure-credit sale can send an empty
            // array — the whole grand total goes on the customer's account. The
            // "credit needs a customer / walk-ins must pay in full" rule is
            // enforced in CompleteSale (CreditRequiresCustomer), per the
            // shape-only convention this request follows.
            'payments'                       => ['present', 'array', 'max:10'],
            'payments.*.payment_method_id'   => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'payments.*.amount'              => ['required', 'numeric', 'gt:0'],
            'payments.*.tendered_amount'     => ['nullable', 'numeric', 'gte:0'],
            'payments.*.change_returned'     => ['nullable', 'numeric', 'gte:0'],
            'payments.*.reference'           => ['nullable', 'string', 'max:191'],
            'payments.*.gateway_provider'    => ['nullable', 'string', 'max:32'],
            'payments.*.gateway_payment_id'  => ['nullable', 'string', 'max:191'],
            'payments.*.gateway_signature'   => ['nullable', 'string', 'max:1000'],
            'payments.*.gateway_status'      => ['nullable', 'string', 'max:32'],
        ];
    }

    /** @return array<string, mixed> */
    public function headerData(): array
    {
        return [
            'store_id'      => (int) $this->input('store_id'),
            'customer_id'   => $this->input('customer_id') !== null && $this->input('customer_id') !== ''
                ? (int) $this->input('customer_id')
                : null,
            'currency_code' => $this->input('currency_code'),
            'sale_date'     => $this->input('sale_date'),
            'local_uuid'    => $this->input('local_uuid'),
            'notes'         => $this->input('notes'),
            'discount_approval' => $this->input('discount_approval'),
            'discount_type'            => $this->input('discount_type'),
            'discount_value'           => $this->input('discount_value'),
            'discount_reason'          => $this->input('discount_reason'),
            'discount_reason_category' => $this->input('discount_reason_category'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function lineData(): array
    {
        return array_values($this->input('items', []));
    }

    /** @return array<int, array<string, mixed>> */
    public function paymentData(): array
    {
        return array_values($this->input('payments', []));
    }
}
