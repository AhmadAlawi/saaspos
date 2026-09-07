<?php

namespace App\Http\Requests\Admin;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape-only validation for POST /admin/sales/{sale}/refund. Business
 * rules (qty <= remaining, sale not already fully refunded, etc.) live
 * in {@see \App\Actions\Sales\RecordSaleReturn}.
 */
class RefundSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Drop rows where the cashier left the refund qty at 0 — those
     * mean "skip this line". Without this filter the `items.*.quantity`
     * gt:0 rule would 422 the request even though the cashier set qty
     * 0 on purpose. If EVERY row is zero, `items.min:1` catches it
     * with a friendlier "pick at least one line" message.
     */
    protected function prepareForValidation(): void
    {
        $items = array_values(array_filter(
            $this->input('items', []),
            fn ($i) => (float) ($i['quantity'] ?? 0) > 0,
        ));
        $this->merge(['items' => $items]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason_code_id'   => ['required', 'integer', Rule::exists('return_reasons', 'id')->where('is_active', true)],
            'refund_method_id' => [
                'nullable', 'integer',
                Rule::exists('payment_methods', 'id')->where('is_active', true),
                // Non-gateway tenders (cash, bank transfer, …) are always
                // allowed. A gateway tender must match one used on the sale —
                // a gateway refund reverses the original charge, so a Stripe
                // charge can't be refunded via Razorpay.
                function (string $attr, $value, \Closure $fail): void {
                    if (! $value) {
                        return;
                    }
                    $method = \App\Models\PaymentMethod::query()->find((int) $value, ['id', 'provider']);
                    if (! $method || ! in_array($method->provider, Sale::GATEWAY_PROVIDERS, true)) {
                        return; // not a gateway tender → always refundable
                    }
                    $sale = $this->route('sale');
                    if (! $sale instanceof Sale) {
                        return;
                    }
                    $usedIds = $sale->payments()
                        ->whereNotNull('payment_method_id')
                        ->pluck('payment_method_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                    if (! in_array((int) $value, $usedIds, true)) {
                        $fail(__('sales.refund.method_not_on_sale'));
                    }
                },
            ],
            'restock'          => ['sometimes', 'boolean'],
            'notes'            => ['nullable', 'string', 'max:5000'],

            'items'                  => ['required', 'array', 'min:1', 'max:200'],
            'items.*.sale_item_id'   => ['required', 'integer', Rule::exists('sale_items', 'id')],
            'items.*.quantity'       => ['required', 'numeric', 'gt:0'],
            'items.*.restock'        => ['nullable', 'boolean'],
            'items.*.notes'          => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Friendly messages for the fields whose default "The reason code id
     * field is required." wording leaks the DB column name into the toast.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason_code_id.required' => __('sales.refund.reason_required'),
            'reason_code_id.integer'  => __('sales.refund.reason_required'),
            'reason_code_id.exists'   => __('sales.refund.reason_required'),
        ];
    }

    /** @return array<string, mixed> */
    public function refundData(int $saleId): array
    {
        return [
            'sale_id'          => $saleId,
            'reason_code_id'   => (int) $this->input('reason_code_id'),
            'refund_method_id' => $this->input('refund_method_id') ? (int) $this->input('refund_method_id') : null,
            'restock'          => $this->boolean('restock', true),
            'notes'            => $this->input('notes') ?: null,
            'items'            => array_values(array_map(fn ($i) => [
                'sale_item_id' => (int) $i['sale_item_id'],
                'quantity'     => (string) $i['quantity'],
                'restock'      => array_key_exists('restock', $i)
                    ? (is_null($i['restock']) ? null : (bool) $i['restock'])
                    : null,
                'notes'        => $i['notes'] ?? null,
            ], $this->input('items', []))),
        ];
    }
}
