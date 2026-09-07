<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates POST /admin/products (create) and PATCH /admin/products/{id}.
 *
 * Cross-field rules enforced here:
 *   - `sku` is unique across non-trashed rows
 *   - `barcode` (if provided) is unique across non-trashed rows
 *   - `mrp` (if provided) must be >= selling_price (legal req in
 *     several markets — printing < MRP is fine, > MRP isn't)
 *   - `pharmacy_schedule` only accepts known drug-schedule codes
 *
 * Image upload + flags are handled in the controller via the shared
 * pattern used by Brand — `image` (file) + `image_remove` (bool).
 */
class ProductRequest extends FormRequest
{
    /** Allowed values for `products.type`. v1 ships `simple` + `variant`;
     *  `kit` and friends arrive in their own dedicated phases. */
    public const TYPES = ['simple', 'variant', 'kit'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The form's "Additional barcodes" field is a plain textarea (one
     * code per line) — friendlier to type/paste into than a repeater —
     * so it posts as `barcodes_text`, not `barcodes[]`. Split it into
     * the `barcodes` array the rest of this class (and the controller's
     * `barcodesPayload()`/`SyncProductBarcodes` call) expects, before
     * validation runs.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('barcodes_text')) {
            $lines = preg_split('/\r\n|\r|\n/', (string) $this->input('barcodes_text', ''));
            $this->merge([
                'barcodes' => array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== '')),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $product = $this->route('product');
        $id      = $product instanceof Product ? $product->id : null;

        return [
            // Type drives which secondary fields the form sends (variants
            // / kit items). Whitelisted to keep schema-level invariants
            // intact (no row can claim to be a type we don't know about).
            'type' => ['nullable', Rule::in(self::TYPES)],

            'sku' => [
                'required', 'string', 'max:64',
                Rule::unique('products', 'sku')
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'barcode' => [
                'nullable', 'string', 'max:64',
                Rule::unique('products', 'barcode')
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],

            // Extra/alternate barcodes beyond the primary one above (a
            // case/carton code from a different supplier, a relabeled
            // batch, etc.) — see App\Models\ProductBarcode. Uniqueness
            // against every OTHER barcode source in the system (this
            // product's own primary barcode, other products' primary/
            // extra barcodes, every variant's barcode) is checked in
            // withValidator() below, same as the variant-sku pattern —
            // it spans multiple tables, so a single Rule::unique can't
            // express it.
            'barcodes'   => ['array'],
            'barcodes.*' => ['nullable', 'string', 'max:64'],
            'name'              => ['required', 'string', 'max:191'],
            'description'       => ['nullable', 'string', 'max:2000'],
            'short_description' => ['nullable', 'string', 'max:191'],

            // Category is mandatory — every sellable item belongs to a
            // department for reporting + cashier navigation. Seed an
            // "Uncategorized" row if a catch-all is needed.
            'category_id'  => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'brand_id'     => ['nullable', 'integer', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'unit_id'      => ['required', 'integer', Rule::exists('units', 'id')->whereNull('deleted_at')],
            'tax_group_id' => ['nullable', 'integer', Rule::exists('tax_groups', 'id')->where('is_active', true)],
            'is_tax_inclusive' => ['sometimes', 'boolean'],

            // Pricing rules:
            //   - Both cost and selling must be > 0. A free SKU is
            //     modelled differently (promotional flag, complimentary
            //     item) — not by pricing it at zero, which corrupts
            //     margin reports downstream.
            //   - cost ≤ selling, otherwise every sale loses money. Equal
            //     is allowed (zero-margin pass-throughs exist).
            //   - MRP, when present, must be ≥ selling — you can't legally
            //     charge more than the printed Maximum Retail Price.
            //
            // `exclude_if:type,variant` drops these entirely for variant
            // products: the parent SKU isn't sold directly, so its price
            // is meaningless — each variant carries its own. Excluding
            // (not just nullable-ing) keeps the parent columns at their
            // DB default 0 and avoids dangling lte/gte references.
            'cost_price'    => ['exclude_if:type,variant', 'required', 'numeric', 'gt:0', 'lte:selling_price', 'max:9999999999.9999'],
            'selling_price' => ['exclude_if:type,variant', 'required', 'numeric', 'gt:0', 'max:9999999999.9999'],
            // Active promo price — the actual charge amount when set. Can
            // legitimately sit below cost_price (that's the whole point of
            // a loss-leader), so it deliberately has no lte:cost_price /
            // gte:cost_price constraint — only that it doesn't exceed the
            // regular selling_price it's discounting from.
            'sale_price'    => ['exclude_if:type,variant', 'nullable', 'numeric', 'gt:0', 'max:9999999999.9999', 'lte:selling_price'],
            'mrp'           => ['exclude_if:type,variant', 'nullable', 'numeric', 'gt:0', 'max:9999999999.9999', 'gte:selling_price'],
            'markup_percent' => ['nullable', 'numeric', 'gte:0', 'lte:9999'],

            // Inventory flags. Defaults match the migration so a minimal
            // payload behaves like a "simple, stock-tracked, sold-each" SKU.
            'sold_by_weight' => ['sometimes', 'boolean'],
            // Weighing-scale item code embedded in scale-printed barcodes
            // (Settings → Scale). Optional; only meaningful for weighed items.
            'scale_plu'      => ['nullable', 'integer', 'min:0', 'max:9999999999'],
            'track_stock'    => ['sometimes', 'boolean'],
            'track_batches'  => ['sometimes', 'boolean'],
            'track_expiry'   => ['sometimes', 'boolean'],
            'reorder_level'    => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'reorder_quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            // YYYY-MM-DD. Past dates are rejected — an expiry that's
            // already lapsed at save time is almost always a typo or a
            // miskey. Pharmacists who actually need to log already-
            // expired stock should do it via a write-off / adjustment
            // flow (inventory module, future).
            'expiry_date'      => ['nullable', 'date', 'after_or_equal:today'],

            // Compliance — optional everywhere except where local law
            // makes them required; we let the operator decide rather
            // than encoding country rules here.
            'hsn_code'          => ['nullable', 'string', 'max:32'],
            // Drug schedule is a dynamic lookup — see DrugSchedule
            // model + admin page. We validate the `code` exists and is
            // currently active so a deactivated row can't be assigned
            // to a new product (existing products keep their codes).
            'pharmacy_schedule' => [
                'nullable',
                'string',
                'max:16',
                Rule::exists('drug_schedules', 'code')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'generic_name'      => ['nullable', 'string', 'max:191'],
            'manufacturer'      => ['nullable', 'string', 'max:191'],

            'is_active'   => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],

            // Image upload — same shape as BrandRequest. Lax so callers
            // that don't send the field (e.g. an inline status toggle)
            // still pass validation.
            'image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
            'image_remove' => ['sometimes', 'boolean'],

            // Variant attribute definitions (type='variant' only). The
            // editor's matrix generator takes the cartesian product of
            // every attribute's values to produce the variant rows.
            //
            //   variant_attributes[*][name]     "Color"
            //   variant_attributes[*][values][] "Red", "Blue", ...
            'variant_attributes'             => ['array'],
            'variant_attributes.*.name'      => ['required_with:variant_attributes', 'string', 'max:50'],
            'variant_attributes.*.values'    => ['array'],
            'variant_attributes.*.values.*'  => ['string', 'max:50'],

            // Variants — array of generated child SKUs. Only meaningful
            // when type='variant'; ignored otherwise. The display label
            // is DERIVED from `combo` server-side, not submitted.
            //
            //   variants[*][id]            existing variant id (omit for new)
            //   variants[*][combo]         { attrName => value } combination map
            //   variants[*][sku]           required, unique across product_variants
            //   variants[*][barcode]       optional, unique when set
            //   variants[*][cost_price]    optional override
            //   variants[*][selling_price] REQUIRED — source of truth at sale time
            //   variants[*][is_active]     bool, default true
            //   variants[*][store_prices]  per-store overrides for THIS variant
            'variants'                   => ['array'],
            'variants.*.id'              => ['nullable', 'integer'],
            'variants.*.combo'           => ['array'],
            'variants.*.combo.*'         => ['nullable', 'string', 'max:50'],
            'variants.*.sku'             => ['required_with:variants', 'string', 'max:64'],
            'variants.*.barcode'         => ['nullable', 'string', 'max:64'],
            'variants.*.cost_price'      => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'variants.*.selling_price'   => ['required_with:variants', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'variants.*.sale_price'      => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'variants.*.mrp'             => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'variants.*.is_active'       => ['sometimes', 'boolean'],
            'variants.*.store_prices'                 => ['array'],
            'variants.*.store_prices.*.cost_price'    => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'variants.*.store_prices.*.selling_price' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'variants.*.store_prices.*.mrp'           => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],

            // Kit components — array of (product, optional variant, qty).
            // Only meaningful when type='kit'; ignored otherwise.
            //
            //   kit_items[*][id]                    existing row id (omit for new)
            //   kit_items[*][component_product_id]  required FK
            //   kit_items[*][component_variant_id]  optional FK (when locking to a variant)
            //   kit_items[*][quantity]              required, > 0
            //   kit_items[*][sort_order]            int, auto if missing
            'kit_items'                          => ['array'],
            'kit_items.*.id'                     => ['nullable', 'integer'],
            'kit_items.*.component_product_id'   => [
                'required_with:kit_items',
                'integer',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'kit_items.*.component_variant_id'   => [
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')->whereNull('deleted_at'),
            ],
            'kit_items.*.quantity'               => ['required_with:kit_items', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'kit_items.*.sort_order'             => ['sometimes', 'integer', 'min:0'],

            // Per-store price overrides — array keyed by store id.
            //   store_prices[<storeId>][cost_price]
            //   store_prices[<storeId>][selling_price]
            //   store_prices[<storeId>][mrp]
            // Each column is independently optional; blank means
            // "inherit from the product-level value".
            'store_prices'                       => ['array'],
            'store_prices.*.cost_price'          => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'store_prices.*.selling_price'       => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'store_prices.*.mrp'                 => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
        ];
    }

    /**
     * Cross-field checks that don't fit declarative rules. Currently:
     *   - When `type === 'variant'`, the product MUST carry at least
     *     one variant — otherwise the parent is unsellable.
     *   - Variant SKUs must be unique among themselves within the
     *     payload (the table-level unique constraint catches duplicates
     *     against OTHER products, but not duplicates within this batch
     *     until insert time, which yields an ugly 500).
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($v) {
            $type     = $this->input('type', 'simple');
            $variants = $this->variantsPayload();

            // A variant product needs at least one generated row, which
            // in turn needs at least one attribute with at least one
            // value. Surface the friendliest message for the empty case.
            if ($type === 'variant' && count($variants) === 0) {
                $v->errors()->add('variants', __('products.errors.variant_requires_one'));
            }

            // Duplicate SKU detection within the payload itself.
            $seen = [];
            foreach ($variants as $i => $row) {
                $sku = strtolower(trim((string) ($row['sku'] ?? '')));
                if ($sku === '') continue;
                if (isset($seen[$sku])) {
                    $v->errors()->add("variants.{$i}.sku", __('products.errors.variant_sku_duplicate', ['sku' => $row['sku']]));
                }
                $seen[$sku] = true;
            }

            // Cross-row uniqueness against other variants already in
            // the DB. On update, the variant ignores its own id so
            // renames stay valid.
            foreach ($variants as $i => $row) {
                $sku = trim((string) ($row['sku'] ?? ''));
                if ($sku === '') continue;

                $variantId = ! empty($row['id']) ? (int) $row['id'] : null;
                $clash = \Illuminate\Support\Facades\DB::table('product_variants')
                    ->where('sku', $sku)
                    ->whereNull('deleted_at')
                    ->when($variantId, fn ($q) => $q->where('id', '!=', $variantId))
                    ->exists();

                if ($clash) {
                    $v->errors()->add("variants.{$i}.sku", __('products.errors.variant_sku_taken', ['sku' => $sku]));
                }
            }

            // Per-variant MRP must be ≥ that variant's selling price —
            // same rule as the product-level mrp/selling_price pairing.
            foreach ($variants as $i => $row) {
                $mrp     = $row['mrp']           ?? null;
                $selling = $row['selling_price'] ?? null;
                if ($mrp !== null && $mrp !== '' && $selling !== null && $selling !== ''
                    && is_numeric($mrp) && is_numeric($selling)
                    && (float) $mrp < (float) $selling) {
                    $v->errors()->add("variants.{$i}.mrp", __('products.errors.variant_mrp_below_selling'));
                }
            }

            // ── Kit-specific checks ─────────────────────────────
            $kitItems = $this->input('kit_items', []) ?? [];
            $kitItems = array_values(array_filter($kitItems, function ($row) {
                return is_array($row) && ! empty($row['component_product_id']);
            }));

            if ($type === 'kit' && count($kitItems) === 0) {
                $v->errors()->add('kit_items', __('products.errors.kit_requires_one'));
            }

            $product = $this->route('product');
            $parentId = $product instanceof Product ? $product->id : null;

            $pairs = [];
            foreach ($kitItems as $i => $row) {
                $componentId = (int) ($row['component_product_id'] ?? 0);
                $variantId   = ! empty($row['component_variant_id']) ? (int) $row['component_variant_id'] : 0;

                // Self-include — a kit referencing itself is a recipe
                // for infinite-loop stock decrements at sale time.
                if ($parentId !== null && $componentId === $parentId) {
                    $v->errors()->add("kit_items.{$i}.component_product_id", __('products.errors.kit_self_include'));
                    continue;
                }

                $key = $componentId.':'.$variantId;
                if (isset($pairs[$key])) {
                    $v->errors()->add("kit_items.{$i}.component_product_id", __('products.errors.kit_duplicate_component'));
                }
                $pairs[$key] = true;

                // If a variant was supplied, it MUST belong to the
                // declared component product — defence against payload
                // tampering and stale form state after re-picking a
                // component.
                if ($variantId !== 0) {
                    $owns = \Illuminate\Support\Facades\DB::table('product_variants')
                        ->where('id', $variantId)
                        ->where('product_id', $componentId)
                        ->whereNull('deleted_at')
                        ->exists();
                    if (! $owns) {
                        $v->errors()->add("kit_items.{$i}.component_variant_id", __('products.errors.kit_variant_mismatch'));
                    }
                }
            }

            // ── Barcode checks — every source (this product's primary
            // barcode, its extra barcodes, and every variant's barcode)
            // must be unique across the WHOLE system, not just within
            // its own column. Variant barcodes had no uniqueness check
            // at all before this — folded in here alongside the new
            // extra-barcode checks since both need the same cross-table
            // scan.
            $primaryBarcode = trim((string) $this->input('barcode', ''));
            $extraBarcodes  = $this->barcodesPayload();

            // Every barcode this SAME submission claims, primary + extra
            // + variants — checked pairwise for duplicates within the
            // payload itself (case-insensitive), same shape as the
            // variant-sku duplicate check above.
            $claimed = [];
            if ($primaryBarcode !== '') {
                $claimed[] = ['path' => 'barcode', 'value' => $primaryBarcode];
            }
            foreach ($extraBarcodes as $code) {
                // Points at `barcodes_text` (the actual form field —
                // one-per-line textarea, see prepareForValidation()),
                // not an index into the array Laravel never renders.
                $claimed[] = ['path' => 'barcodes_text', 'value' => $code];
            }
            foreach ($variants as $i => $row) {
                $code = trim((string) ($row['barcode'] ?? ''));
                if ($code !== '') {
                    $claimed[] = ['path' => "variants.{$i}.barcode", 'value' => $code];
                }
            }

            $seenBarcodes = [];
            foreach ($claimed as $entry) {
                $key = strtolower($entry['value']);
                if (isset($seenBarcodes[$key])) {
                    $v->errors()->add($entry['path'], __('products.errors.barcode_duplicate_in_payload', ['barcode' => $entry['value']]));
                }
                $seenBarcodes[$key] = true;
            }

            // Against the rest of the system: any OTHER product's primary
            // barcode, any product's extra barcode row (excluding this
            // product's own — those get fully replaced by this save
            // anyway), and any variant's barcode (excluding this
            // product's own variants being re-saved by id).
            $variantIdsThisProduct = $parentId
                ? \Illuminate\Support\Facades\DB::table('product_variants')->where('product_id', $parentId)->pluck('id')
                : collect();

            foreach ($claimed as $entry) {
                $code = $entry['value'];

                $clashesProduct = \Illuminate\Support\Facades\DB::table('products')
                    ->where('barcode', $code)
                    ->whereNull('deleted_at')
                    ->when($parentId, fn ($q) => $q->where('id', '!=', $parentId))
                    ->exists();

                $clashesExtra = \Illuminate\Support\Facades\DB::table('product_barcodes')
                    ->where('barcode', $code)
                    ->when($parentId, fn ($q) => $q->where('product_id', '!=', $parentId))
                    ->exists();

                $clashesVariant = \Illuminate\Support\Facades\DB::table('product_variants')
                    ->where('barcode', $code)
                    ->whereNull('deleted_at')
                    ->when($variantIdsThisProduct->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $variantIdsThisProduct))
                    ->exists();

                if ($clashesProduct || $clashesExtra || $clashesVariant) {
                    $v->errors()->add($entry['path'], __('products.errors.barcode_taken', ['barcode' => $code]));
                }
            }
        });
    }

    /**
     * Normalize the validated payload for persistence on the
     * `products` row itself. Slug is derived from the name; boolean
     * flags are coerced; `type` falls back to 'simple' when missing.
     *
     * Returns ONLY the product-table columns — variants[] and image
     * upload fields are stripped (the controller handles them
     * through dedicated actions).
     *
     * @return array<string, mixed>
     */
    public function persistedAttributes(): array
    {
        $data = $this->validated();

        // Slug — append id (or a random tail on create) for global
        // uniqueness without forcing the operator to manage the slug
        // column themselves.
        $base    = Str::slug($data['name']);
        $product = $this->route('product');
        $data['slug'] = $product instanceof Product
            ? $base.'-'.$product->id
            : $base.'-'.Str::lower(Str::random(5));

        // Coerce every boolean flag — `boolean()` reads from the raw
        // input, so an unchecked checkbox lands as `false` not absent.
        foreach (['is_tax_inclusive', 'sold_by_weight', 'track_stock', 'track_batches', 'track_expiry', 'is_active', 'is_featured'] as $flag) {
            $data[$flag] = $this->boolean($flag);
        }

        // Default type for missing input. Whitelisted by the rule
        // above so we know it's one of self::TYPES if present.
        $data['type'] = $data['type'] ?? 'simple';

        // Persist variant attribute definitions into meta for variant
        // products; clear them otherwise so a type-down-switch doesn't
        // leave stale attributes behind. Merge into any existing meta
        // so we don't clobber unrelated keys.
        $existing = ($product instanceof Product && is_array($product->meta)) ? $product->meta : [];
        if ($data['type'] === 'variant') {
            $existing['variant_attributes'] = $this->variantAttributesPayload();
        } else {
            unset($existing['variant_attributes']);
        }
        $data['meta'] = $existing ?: null;

        // Drop fields handled separately by the controller / actions:
        //   - `image` + `image_remove`  → controller stores file
        //   - `variants`                → SyncProductVariants action
        //   - `variant_attributes`      → folded into meta above
        //   - `kit_items`               → SyncProductKitItems action
        //   - `store_prices`            → SyncProductPrices action
        unset(
            $data['image'],
            $data['image_remove'],
            $data['variants'],
            $data['variant_attributes'],
            $data['kit_items'],
            $data['store_prices'],
        );

        return $data;
    }

    /**
     * Returns the validated variants payload (or empty array). Sanitised
     * — rows with no SKU are dropped (the matrix may hold blank slots
     * the user hasn't filled, but a variant with no SKU can't persist).
     *
     * @return array<int, array<string, mixed>>
     */
    public function variantsPayload(): array
    {
        $rows = $this->input('variants', []) ?? [];
        return array_values(array_filter($rows, function ($row) {
            return is_array($row) && ! empty(trim((string) ($row['sku'] ?? '')));
        }));
    }

    /** Cleaned extra-barcode list: trimmed, blanks dropped. */
    public function barcodesPayload(): array
    {
        $rows = $this->input('barcodes', []) ?? [];
        return array_values(array_filter(array_map(
            fn ($b) => trim((string) $b),
            $rows,
        ), fn ($b) => $b !== ''));
    }

    /**
     * Returns the cleaned variant-attribute definitions:
     *   [ ['name' => 'Color', 'values' => ['Red','Blue']], ... ]
     *
     * Attributes with a blank name or no values are dropped; values are
     * trimmed and de-duplicated (case-insensitively) so the matrix
     * generator doesn't emit duplicate combinations.
     *
     * @return array<int, array{name: string, values: array<int, string>}>
     */
    public function variantAttributesPayload(): array
    {
        $rows = $this->input('variant_attributes', []) ?? [];
        if (! is_array($rows)) return [];

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) continue;
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') continue;

            $values = [];
            $seen   = [];
            foreach ((array) ($row['values'] ?? []) as $val) {
                $val = trim((string) $val);
                if ($val === '') continue;
                $key = strtolower($val);
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $values[]   = $val;
            }
            if ($values === []) continue;

            $out[] = ['name' => $name, 'values' => $values];
        }
        return $out;
    }

    /**
     * Returns the validated kit-items payload, with empty / partial
     * rows dropped. A row counts as "real" only when it names a
     * component product — anything else is leftover form state.
     *
     * @return array<int, array<string, mixed>>
     */
    public function kitItemsPayload(): array
    {
        $rows = $this->input('kit_items', []) ?? [];
        return array_values(array_filter($rows, function ($row) {
            return is_array($row) && ! empty($row['component_product_id']);
        }));
    }

    /**
     * Returns the validated per-store price overrides payload, keyed
     * by store id. Rows where all three columns are blank are kept —
     * the sync action interprets "all-blank" as "delete the override
     * row" so the user can clear an override from the UI.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function storePricesPayload(): array
    {
        $rows = $this->input('store_prices', []) ?? [];
        if (! is_array($rows)) return [];

        $out = [];
        foreach ($rows as $storeId => $row) {
            if (! is_array($row)) continue;
            $out[(int) $storeId] = [
                'cost_price'    => isset($row['cost_price'])    && $row['cost_price']    !== '' ? $row['cost_price']    : null,
                'selling_price' => isset($row['selling_price']) && $row['selling_price'] !== '' ? $row['selling_price'] : null,
                'mrp'           => isset($row['mrp'])           && $row['mrp']           !== '' ? $row['mrp']           : null,
            ];
        }
        return $out;
    }
}
