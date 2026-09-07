<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from_store_id'         => ['required', 'integer', Rule::exists('stores', 'id')->where('is_active', true)],
            'to_store_id'           => ['required', 'integer', Rule::exists('stores', 'id')->where('is_active', true), Rule::notIn([$this->input('from_store_id')])],
            'transfer_date'         => ['required', 'date'],
            'expected_arrival_date' => ['nullable', 'date', 'after_or_equal:transfer_date'],
            'notes'                 => ['nullable', 'string', 'max:1000'],

            'items'                          => ['required', 'array', 'min:1'],
            'items.*.product_id'             => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.variant_id'             => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            // Bound to the DECIMAL(15,4) range so a stray value (e.g. a barcode
            // in the qty field) is rejected with an error, not a DB crash.
            'items.*.requested_quantity'     => ['required', 'numeric', 'gt:0', 'max:'.StockTakeRequest::QTY_MAX],
            'items.*.unit_cost'              => ['nullable', 'numeric', 'min:0', 'max:'.StockTakeRequest::QTY_MAX],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to_store_id.not_in'    => __('inventory.transfers.errors.same_store'),
            'items.required'        => __('inventory.transfers.errors.items_required'),
            'items.min'             => __('inventory.transfers.errors.items_required'),
            'items.*.requested_quantity.gt' => __('inventory.transfers.errors.quantity_zero'),
        ];
    }

    /**
     * Friendly field names so validation reads "The destination store
     * field is required" instead of "The to store id field is required".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from_store_id'              => __('inventory.transfers.fields.from_store'),
            'to_store_id'                => __('inventory.transfers.fields.to_store'),
            'transfer_date'              => __('inventory.transfers.fields.transfer_date'),
            'items.*.product_id'         => __('inventory.transfers.items.col_product'),
            'items.*.requested_quantity' => __('inventory.transfers.items.col_qty'),
            'items.*.unit_cost'          => __('inventory.transfers.items.col_cost'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function persistedAttributes(): array
    {
        $data = $this->validated();

        $data['items'] = array_map(fn (array $line) => [
            'product_id'         => (int) $line['product_id'],
            'variant_id'         => isset($line['variant_id']) && $line['variant_id'] !== '' ? (int) $line['variant_id'] : null,
            'requested_quantity' => (float) $line['requested_quantity'],
            'unit_cost'          => isset($line['unit_cost']) && $line['unit_cost'] !== '' ? (float) $line['unit_cost'] : null,
        ], array_values($data['items']));

        return $data;
    }
}
