<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'store_id'        => ['required', 'integer', Rule::exists('stores', 'id')->where('is_active', true)],
            'adjustment_date' => ['required', 'date'],
            'reason_code_id'  => ['nullable', 'integer', Rule::exists('stock_adjustment_reasons', 'id')->where('is_active', true)],
            'reason'          => ['nullable', 'string', 'max:191'],
            'notes'           => ['nullable', 'string', 'max:1000'],

            'items'                    => ['required', 'array', 'min:1'],
            'items.*.product_id'       => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.variant_id'       => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'items.*.batch_id'         => ['nullable', 'integer', Rule::exists('product_batches', 'id')],
            // New-batch capture (mirrors purchase-receive). A line carries
            // EITHER an existing batch_id OR these fields to create one at
            // post time — never both (batch_id wins in persistedAttributes).
            'items.*.batch_number'     => ['nullable', 'string', 'max:64'],
            'items.*.manufacture_date' => ['nullable', 'date'],
            'items.*.expiry_date'      => ['nullable', 'date', 'after_or_equal:items.*.manufacture_date'],
            // Delta is signed; bound BOTH ends to the DECIMAL(15,4) range so a
            // stray value (e.g. a barcode in the qty field) can't crash the DB.
            'items.*.quantity_delta'   => ['required', 'numeric', 'not_in:0', 'min:-'.StockTakeRequest::QTY_MAX, 'max:'.StockTakeRequest::QTY_MAX],
            'items.*.unit_cost'        => ['nullable', 'numeric', 'min:0', 'max:'.StockTakeRequest::QTY_MAX],
            'items.*.notes'            => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * Cross-field guard: a new batch (batch_number, no batch_id) only makes
     * sense on an inflow line. You can't materialise a batch and remove
     * stock from it in the same breath, so reject a new batch on an "Out"
     * (negative delta) line. The UI already hides the option for Out lines;
     * this is the server-side backstop.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('items', []) as $i => $line) {
                $hasNewBatch = ! empty($line['batch_number']) && empty($line['batch_id']);
                $delta       = isset($line['quantity_delta']) ? (float) $line['quantity_delta'] : 0;
                if ($hasNewBatch && $delta <= 0) {
                    $validator->errors()->add(
                        "items.{$i}.batch_number",
                        __('inventory.adjustments.errors.new_batch_in_only'),
                    );
                }
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required'             => __('inventory.adjustments.errors.items_required'),
            'items.*.quantity_delta.not_in' => __('inventory.adjustments.errors.quantity_zero'),
        ];
    }

    /**
     * Payload shape consumed by Create/UpdateStockAdjustment.
     *
     * @return array<string, mixed>
     */
    public function persistedAttributes(): array
    {
        $data = $this->validated();

        // Normalise the items: drop empty rows and cast numerics.
        $data['items'] = array_map(function (array $line) {
            $batchId = isset($line['batch_id']) && $line['batch_id'] !== '' ? (int) $line['batch_id'] : null;
            // An existing-batch pick wins: ignore any new-batch fields that
            // may have lingered in the payload so we never both reference a
            // batch and try to create one on the same line.
            $newBatchNumber = $batchId === null && ! empty($line['batch_number']) ? trim((string) $line['batch_number']) : null;

            return [
                'product_id'       => (int) $line['product_id'],
                'variant_id'       => isset($line['variant_id']) && $line['variant_id'] !== '' ? (int) $line['variant_id'] : null,
                'batch_id'         => $batchId,
                'batch_number'     => $newBatchNumber,
                'manufacture_date' => $newBatchNumber !== null && ! empty($line['manufacture_date']) ? $line['manufacture_date'] : null,
                'expiry_date'      => $newBatchNumber !== null && ! empty($line['expiry_date']) ? $line['expiry_date'] : null,
                'quantity_delta'   => (float) $line['quantity_delta'],
                'unit_cost'        => isset($line['unit_cost']) && $line['unit_cost'] !== '' ? (float) $line['unit_cost'] : null,
                'notes'            => $line['notes'] ?? null,
            ];
        }, array_values($data['items']));

        return $data;
    }
}
