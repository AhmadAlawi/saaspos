<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Customer-payment create form validator. Shape mirrors
 * {@see SupplierPaymentRequest}: required header + optional allocations,
 * with allocations allowing pure-credit (no-allocation) advance payments.
 *
 * Business rules — sum-vs-amount, per-sale balance, customer ownership —
 * are enforced by the action ({@see App\Actions\Sales\RecordCustomerPayments})
 * via RuntimeException, which the controller surfaces as a 422 / flash.
 */
class CustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id'       => ['required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'payment_date'      => ['required', 'date', 'before_or_equal:today'],
            'amount'            => ['required', 'numeric', 'gt:0'],
            'reference'         => ['nullable', 'string', 'max:191'],
            'notes'             => ['nullable', 'string', 'max:5000'],

            'allocations'              => ['nullable', 'array', 'max:200'],
            'allocations.*.sale_id'    => ['required', 'integer', Rule::exists('sales', 'id')->whereNull('deleted_at')],
            'allocations.*.amount'     => ['required', 'numeric', 'gt:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_id'       => mb_strtolower(__('customer_payments.fields.customer')),
            'payment_method_id' => mb_strtolower(__('customer_payments.fields.method')),
            'payment_date'      => mb_strtolower(__('customer_payments.fields.date')),
            'reference'         => mb_strtolower(__('customer_payments.fields.reference')),
            'notes'             => mb_strtolower(__('customer_payments.fields.notes')),
            'amount'            => mb_strtolower(__('customer_payments.totals.amount')),
            'allocations'       => mb_strtolower(__('customer_payments.sections.allocations')),
        ];
    }

    /** @return array<string, mixed> */
    public function headerData(): array
    {
        return $this->safe()->except(['allocations']);
    }

    /** @return array<int, array<string, mixed>> */
    public function allocationData(): array
    {
        return array_values($this->validated()['allocations'] ?? []);
    }
}
