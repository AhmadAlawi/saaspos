<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseReturnRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'return_date'              => ['required', 'date'],
            'notes'                    => ['nullable', 'string', 'max:1000'],
            'items'                    => ['required', 'array'],
            'items.*.purchase_item_id' => ['required', 'integer', 'exists:purchase_items,id'],
            'items.*.quantity'         => ['required', 'numeric', 'min:0'],
            'items.*.restock'          => ['nullable', 'boolean'],
            'items.*.notes'            => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'return_date' => __('purchases.returns.fields.return_date'),
        ];
    }
}
