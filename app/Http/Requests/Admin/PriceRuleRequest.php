<?php

namespace App\Http\Requests\Admin;

use App\Models\PriceRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PriceRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:150'],
            'scope'          => ['required', Rule::in([PriceRule::SCOPE_ALL, PriceRule::SCOPE_CATEGORY, PriceRule::SCOPE_PRODUCT])],
            'category_id'    => ['nullable', 'integer', 'exists:categories,id'],
            'product_id'     => ['nullable', 'integer', 'exists:products,id'],
            'store_id'       => ['nullable', 'integer', 'exists:stores,id'],
            'discount_type'  => ['required', Rule::in([PriceRule::TYPE_PERCENT, PriceRule::TYPE_AMOUNT])],
            'discount_value' => ['required', 'numeric', 'gt:0'],
            'starts_at'      => ['required', 'date'],
            'ends_at'        => ['required', 'date', 'after_or_equal:starts_at'],
            'is_active'      => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $scope = $this->input('scope');
            if ($scope === PriceRule::SCOPE_CATEGORY && ! $this->input('category_id')) {
                $v->errors()->add('category_id', __('price_rules.errors.category_required'));
            }
            if ($scope === PriceRule::SCOPE_PRODUCT && ! $this->input('product_id')) {
                $v->errors()->add('product_id', __('price_rules.errors.product_required'));
            }
            if ($this->input('discount_type') === PriceRule::TYPE_PERCENT
                && (float) $this->input('discount_value', 0) > 100) {
                $v->errors()->add('discount_value', __('price_rules.errors.percent_max'));
            }
        });
    }

    /** @return array<string, mixed> */
    public function persistedAttributes(): array
    {
        $scope = (string) $this->input('scope');

        return [
            'name'           => trim((string) $this->input('name')),
            'scope'          => $scope,
            'category_id'    => $scope === PriceRule::SCOPE_CATEGORY ? (int) $this->input('category_id') : null,
            'product_id'     => $scope === PriceRule::SCOPE_PRODUCT ? (int) $this->input('product_id') : null,
            'store_id'       => $this->input('store_id') !== null && $this->input('store_id') !== '' ? (int) $this->input('store_id') : null,
            'discount_type'  => (string) $this->input('discount_type'),
            'discount_value' => (string) $this->input('discount_value'),
            'starts_at'      => $this->input('starts_at'),
            'ends_at'        => $this->input('ends_at'),
            'is_active'      => $this->boolean('is_active', true),
        ];
    }
}
