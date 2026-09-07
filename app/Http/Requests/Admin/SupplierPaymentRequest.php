<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the supplier-payment create form. Genuinely required (per
 * [[deferred-validation]]):
 *   - supplier_id, store_id, payment_method_id
 *   - payment_date, amount > 0
 *
 * `allocations` is OPTIONAL since Slice 4b: a pure-credit payment (advance
 * to supplier) ships with no allocations, and an over-allocated payment
 * has the leftover land as credit. The action enforces the sum invariants.
 *
 * Validates the SHAPE — the action ({@see App\Actions\Purchases\RecordSupplierPayment})
 * is the source of truth on business rules (sum-vs-amount, per-PO balance
 * check, status eligibility). Surfacing those at form-validate time too
 * would mean two implementations to keep in sync; the action's
 * RuntimeExceptions get caught in the controller and turned into flash
 * messages.
 */
class SupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'supplier_id'       => ['required', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'store_id'          => ['required', 'integer', Rule::exists('stores', 'id')],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'payment_date'      => ['required', 'date', 'before_or_equal:today'],
            'amount'            => ['required', 'numeric', 'gt:0'],
            'reference'         => ['nullable', 'string', 'max:191'],
            'notes'             => ['nullable', 'string', 'max:5000'],

            // Optional for pure-credit / advance payments (Slice 4b). When
            // present, every row must be well-formed; the action enforces
            // the sum-vs-amount and per-PO balance invariants.
            'allocations'                 => ['nullable', 'array', 'max:200'],
            'allocations.*.purchase_id'   => ['required', 'integer', Rule::exists('purchases', 'id')->whereNull('deleted_at')],
            'allocations.*.amount'        => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * Friendly attribute names — Laravel's default snake_case-to-words
     * conversion produces noise like "store id" / "payment method id".
     * Override to the same lang keys the form fields use so the error
     * toast reads "Store" / "Payment method" instead.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id'       => mb_strtolower(__('supplier_payments.fields.supplier')),
            'store_id'          => mb_strtolower(__('supplier_payments.fields.store')),
            'payment_method_id' => mb_strtolower(__('supplier_payments.fields.method')),
            'payment_date'      => mb_strtolower(__('supplier_payments.fields.date')),
            'reference'         => mb_strtolower(__('supplier_payments.fields.reference')),
            'notes'             => mb_strtolower(__('supplier_payments.fields.notes')),
            'amount'            => mb_strtolower(__('supplier_payments.totals.amount')),
            'allocations'       => mb_strtolower(__('supplier_payments.sections.allocations')),
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
