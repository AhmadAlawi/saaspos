<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape-only validation for create + update of a Stock Take.
 *
 * `store_id` + `take_date` are required on create. `name` / `notes` are
 * optional metadata. The items array isn't accepted on create — items
 * are auto-snapshotted from `product_stock_levels` inside
 * {@see \App\Actions\Inventory\CreateStockTake}. On update, items[]
 * is the sparse list of counted-quantity changes per row.
 */
class StockTakeRequest extends FormRequest
{
    /** Largest value a DECIMAL(15,4) quantity column can hold. Anything bigger
     *  (a barcode scanned into a qty field, a fat-fingered paste) must be
     *  rejected with a friendly error instead of crashing MySQL. */
    public const QTY_MAX = '99999999999.9999';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isCreate = $this->isMethod('post') && ! $this->route('stockTake');

        return [
            'store_id'  => [$isCreate ? 'required' : 'sometimes', 'integer', Rule::exists('stores', 'id')->where('is_active', true)],
            'take_date' => [$isCreate ? 'required' : 'sometimes', 'date'],
            'name'      => ['nullable', 'string', 'max:191'],
            'notes'     => ['nullable', 'string', 'max:1000'],

            // Items only sent on update. Each entry references an existing item
            // by `id`; counted_quantity can be null (= skipped) or any
            // non-negative number incl. zero — but MUST fit DECIMAL(15,4), or a
            // stray value (e.g. a barcode typed into the field) crashes the DB.
            'items'                    => ['sometimes', 'array', 'max:5000'],
            'items.*.id'               => ['required', 'integer'],
            'items.*.counted_quantity' => ['nullable', 'numeric', 'min:0', 'max:'.self::QTY_MAX],
            'items.*.notes'            => ['nullable', 'string', 'max:191'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'store_id.required'                => __('stock_takes.errors.store_required'),
            'take_date.required'               => __('stock_takes.errors.date_required'),
            'items.*.counted_quantity.max'     => __('stock_takes.errors.count_too_large'),
            'items.*.counted_quantity.numeric' => __('stock_takes.errors.count_not_numeric'),
            'items.*.counted_quantity.min'     => __('stock_takes.errors.count_negative'),
        ];
    }

    /**
     * Header attributes consumed by CreateStockTake.
     *
     * @return array<string, mixed>
     */
    public function headerAttributes(): array
    {
        $data = $this->validated();
        return [
            'store_id'  => (int) $data['store_id'],
            'take_date' => $data['take_date'],
            'name'      => $data['name']  ?? null,
            'notes'     => $data['notes'] ?? null,
        ];
    }

    /**
     * Update payload consumed by UpdateStockTake.
     *
     * @return array<string, mixed>
     */
    public function updateAttributes(): array
    {
        $data = $this->validated();
        $out = [
            'name'      => $data['name']      ?? null,
            'take_date' => $data['take_date'] ?? null,
            'notes'     => $data['notes']     ?? null,
        ];
        if (!empty($data['items'] ?? [])) {
            $out['items'] = array_map(fn ($r) => [
                'id'               => (int) $r['id'],
                'counted_quantity' => $r['counted_quantity'] ?? null,
                'notes'            => $r['notes'] ?? null,
            ], $data['items']);
        }
        return $out;
    }
}
