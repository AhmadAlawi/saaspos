<?php

namespace App\Http\Requests\Admin;

use App\Models\Purchase;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Validates the purchase create / edit form (draft).
 *
 * Per [[deferred-validation]] memory: only require what the entity
 * genuinely needs to exist as a draft —
 *   - supplier
 *   - store
 *   - purchase_date
 *   - currency_code
 *   - at least one line item with product + quantity + unit_cost
 *
 * Everything else (due_date, supplier_invoice_number, batch + expiry,
 * tax_group, discount, notes) is nullable. Per-line tax_group is
 * captured but not coerced — server recomputes line totals from the
 * tax group on save (see {@see App\Actions\Purchases\ComputePurchaseTotals}).
 */
class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'supplier_id'             => ['required', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'store_id'                => ['required', 'integer', Rule::exists('stores', 'id')],
            'purchase_date'           => ['required', 'date'],
            'due_date'                => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:64'],
            'currency_code'           => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'exchange_rate_to_base'   => ['nullable', 'numeric', 'min:0'],
            'notes'                   => ['nullable', 'string', 'max:5000'],

            // Single invoice attachment (optional). Replaces any existing
            // one. `remove_attachment` clears it without uploading a new
            // file. Stored privately; streamed via an auth-gated route.
            'attachment'              => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv'],
            'remove_attachment'       => ['nullable', 'boolean'],

            'items'                   => ['required', 'array', 'min:1', 'max:500'],
            'items.*.product_id'      => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.variant_id'      => [
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')->whereNull('deleted_at'),
                // Cross-field rule: the variant must belong to the
                // same product as this line's `product_id`. Without
                // this, a crafted submission can pair `product_id=1`
                // with `variant_id=99-from-product-7` and ReceivePurchase
                // happily writes a stock movement under the mismatched
                // pair — corrupting both products' WAC and stock.
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! preg_match('/^items\.(\d+)\.variant_id$/', $attribute, $m)) {
                        return;
                    }
                    $productId = $this->input("items.{$m[1]}.product_id");
                    if ($productId === null || $productId === '') {
                        return;
                    }
                    $belongs = DB::table('product_variants')
                        ->where('id', $value)
                        ->where('product_id', $productId)
                        ->whereNull('deleted_at')
                        ->exists();
                    if (! $belongs) {
                        $fail(__('purchases.errors.variant_product_mismatch'));
                    }
                },
            ],
            'items.*.quantity'        => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost'       => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent'=> ['nullable', 'numeric', 'between:0,100'],
            'items.*.tax_group_id'    => ['nullable', 'integer', Rule::exists('tax_groups', 'id')],
            'items.*.batch_number'    => ['nullable', 'string', 'max:64'],
            'items.*.manufacture_date'=> ['nullable', 'date'],
            'items.*.expiry_date'     => ['nullable', 'date'],
        ];
    }

    /**
     * Human-friendly field names so validation messages read
     * "The supplier field is required" instead of "The supplier id field
     * is required" / "The items.0.unit_cost field is required".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id'              => __('purchases.validation_attributes.supplier'),
            'store_id'                 => __('purchases.validation_attributes.store'),
            'purchase_date'            => __('purchases.validation_attributes.purchase_date'),
            'due_date'                 => __('purchases.validation_attributes.due_date'),
            'currency_code'            => __('purchases.validation_attributes.currency'),
            'items.*.product_id'       => __('purchases.validation_attributes.product'),
            'items.*.quantity'         => __('purchases.validation_attributes.quantity'),
            'items.*.unit_cost'        => __('purchases.validation_attributes.unit_cost'),
            'items.*.discount_percent' => __('purchases.validation_attributes.discount'),
            'items.*.tax_group_id'     => __('purchases.validation_attributes.tax'),
        ];
    }

    /** @return array<string, mixed> */
    public function headerData(): array
    {
        // `attachment` (the uploaded file) and `remove_attachment` are NOT
        // columns on `purchases` — the controller's syncAttachment() handles
        // them. Excluding them here keeps the UploadedFile out of the purchase
        // mass-assignment (otherwise it's cast to its tmp path and the insert
        // fails on an unknown `attachment` column).
        $data = $this->safe()->except(['items', 'attachment', 'remove_attachment']);
        $data['currency_code'] = strtoupper((string) $data['currency_code']);
        if (! isset($data['exchange_rate_to_base']) || $data['exchange_rate_to_base'] === '' || $data['exchange_rate_to_base'] === null) {
            $data['exchange_rate_to_base'] = '1';
        }
        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    public function itemData(): array
    {
        return array_values($this->validated()['items'] ?? []);
    }
}
