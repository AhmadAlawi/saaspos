<?php

namespace App\Actions\Products;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\TaxGroup;
use App\Models\Unit;
use App\Services\Excel\SpreadsheetReader;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Streaming CSV / XLSX → products importer.
 *
 * Two modes share one parsing engine:
 *
 *   - `preview(path)`  → reads headers + the first N data rows, returns
 *     a structure the wizard UI uses to render the column-mapping step.
 *     Side-effect free (nothing touches the products table).
 *
 *   - `commit(path, mapping)` → re-reads the whole file using the user's
 *     confirmed column-to-field mapping, validates each row, and creates
 *     or updates products. Returns a summary (created / updated / errors).
 *
 * Identity: rows match on SKU. A row whose SKU matches an existing
 * product becomes an update; a row whose SKU is new becomes a create.
 * Empty / blank SKUs are skipped with an error.
 *
 * Foreign-key columns (`category`, `brand`, `unit`, `tax_group`) are
 * matched by NAME (case-insensitive). A blank value leaves the column
 * null on the product; an unknown name is reported as a row error.
 *
 * v1 scope: top-level product fields only. Variants / kits / per-store
 * prices are not importable yet — they need a different mapping shape
 * and a separate wizard step.
 *
 * Hook points:
 *   - filter `products.import.row`    → adjust a normalized payload pre-validation
 *   - action `products.before_import` → fires before the loop opens
 *   - action `products.after_import`  → fires after the loop closes with the summary
 */
class ImportProductsFromCsv
{
    /** Importable target fields. Order drives the wizard's
     *  "map column to..." dropdown. */
    public const TARGET_FIELDS = [
        'sku', 'name', 'barcode',
        'category', 'brand', 'unit', 'tax_group',
        'cost_price', 'selling_price', 'sale_price', 'mrp',
        'description', 'short_description',
        'track_stock', 'sold_by_weight', 'track_batches', 'track_expiry',
        'reorder_level', 'reorder_quantity',
        'hsn_code', 'pharmacy_schedule', 'generic_name', 'manufacturer',
        'is_active', 'is_featured',
        // Declared image FILENAME (e.g. "apple-front.jpg") — recorded in
        // products.image_ref so the Bulk images screen can match an uploaded
        // file to this product. Not the image itself.
        'image',
    ];

    /** Maximum data rows surfaced in the preview step. The wizard
     *  shows them as a small table for visual confirmation. */
    public const PREVIEW_ROWS = 10;

    public function __construct(private SpreadsheetReader $reader) {}

    /**
     * Side-effect-free preview of an uploaded spreadsheet.
     *
     * Returns:
     *   [
     *     'headers'         => [<col 0 string>, ...],
     *     'suggested_map'   => [<header> => <target field or null>, ...],
     *     'rows'            => [[col0, col1, ...], ...],  // first PREVIEW_ROWS data rows
     *     'total_columns'   => int,
     *   ]
     *
     * @return array{headers: array<int, string>, suggested_map: array<string, ?string>, rows: array<int, array<int, string>>, total_columns: int}
     */
    public function preview(string $path): array
    {
        $headers = [];
        $sample  = [];
        foreach ($this->reader->rows($path, self::PREVIEW_ROWS + 1) as $i => $row) {
            if ($i === 0) {
                $headers = $row;
                continue;
            }
            $sample[] = $row;
        }

        $suggested = [];
        foreach ($headers as $h) {
            $suggested[$h] = $this->suggestField($h);
        }

        return [
            'headers'        => $headers,
            'suggested_map'  => $suggested,
            'rows'           => $sample,
            'total_columns'  => count($headers),
        ];
    }

    /**
     * Apply the import.
     *
     * `$mapping` is column-index → target-field. Indexes that map to
     * null are ignored. The wizard sends the indexes as numeric strings
     * via the form, so we normalize before use.
     *
     * @param array<int|string, ?string> $mapping
     * @return array{created: int, updated: int, skipped: int, errors: array<int, array{row: int, message: string}>}
     */
    public function commit(string $path, array $mapping, ?int $userId = null): array
    {
        do_action('products.before_import', $path, $mapping);

        $mapping = $this->normalizeMapping($mapping);
        if (! in_array('sku', $mapping, true)) {
            throw new \RuntimeException('SKU column must be mapped — every product row needs a SKU to identify it.');
        }

        $lookups = $this->loadLookups();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];

        // 1-based for human-friendly error reporting (row 1 = header,
        // row 2 = first data row).
        $rowNum = 0;

        foreach ($this->reader->rows($path) as $i => $cells) {
            $rowNum++;
            if ($i === 0) continue; // header

            $errorCountBefore = count($errors);
            $payload = $this->buildPayload($cells, $mapping, $lookups, $errors, $rowNum);
            if ($payload === null) {
                $skipped++;
                continue;
            }
            // Lookup miss (unknown category / brand / unit / tax) is
            // treated as a hard skip — the user mapped the column on
            // purpose; silently creating the row with a null FK would
            // mask the data-quality issue.
            if (count($errors) > $errorCountBefore) {
                $skipped++;
                continue;
            }

            $payload = apply_filters('products.import.row', $payload, $cells, $mapping);

            $existing = Product::query()->where('sku', $payload['sku'])->first();

            $validator = $this->validateRow($payload, $existing);
            if ($validator->fails()) {
                $errors[] = [
                    'row'     => $rowNum,
                    'message' => $validator->errors()->first(),
                ];
                $skipped++;
                continue;
            }

            try {
                DB::transaction(function () use (&$created, &$updated, $existing, $payload, $userId) {
                    // Free a SKU/barcode still held by a trashed product so
                    // the insert/rename doesn't hit the all-rows unique index
                    // (mirrors CreateProduct / UpdateProduct).
                    $this->reclaimSoftDeletedIdentifiers(
                        $payload['sku'] ?? null,
                        $payload['barcode'] ?? null,
                        $existing?->id,
                    );

                    if ($existing) {
                        $payload['updated_by'] = $userId;
                        $existing->fill($payload)->save();
                        $updated++;
                    } else {
                        $payload['slug']       = Str::slug($payload['name']).'-'.Str::lower(Str::random(5));
                        $payload['created_by'] = $userId;
                        $payload['updated_by'] = $userId;
                        Product::create($payload);
                        $created++;
                    }
                });
            } catch (QueryException) {
                // Last-resort net — any DB constraint we didn't validate
                // for (race condition, an index we don't know about) becomes
                // a skippable row error instead of aborting the whole import.
                $errors[] = [
                    'row'     => $rowNum,
                    'message' => "Row {$rowNum} could not be saved (database constraint). Check for duplicate values.",
                ];
                $skipped++;
                continue;
            }
        }

        $summary = [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors'  => $errors,
        ];

        do_action('products.after_import', $summary);

        return $summary;
    }

    /* ── Internals ─────────────────────────────────────────────── */

    /**
     * Best-guess mapping for a header string. Used only to seed the UI
     * — the user can override any suggestion before committing.
     */
    private function suggestField(string $header): ?string
    {
        $needle = $this->canonical($header);

        $aliases = [
            'sku'              => ['sku', 'code', 'productsku', 'item', 'itemcode'],
            'name'             => ['name', 'productname', 'title', 'displayname'],
            'barcode'          => ['barcode', 'ean', 'upc', 'gtin'],
            'category'         => ['category', 'cat', 'department'],
            'brand'            => ['brand', 'make', 'manufacturerbrand'],
            'unit'             => ['unit', 'uom', 'soldby'],
            'tax_group'        => ['tax', 'taxrule', 'taxgroup', 'vat'],
            'cost_price'       => ['cost', 'costprice', 'buyprice', 'purchaseprice'],
            'selling_price'    => ['price', 'sellingprice', 'sellprice', 'retailprice', 'msrp'],
            'sale_price'       => ['saleprice', 'discountprice', 'offerprice', 'promoprice'],
            'mrp'              => ['mrp', 'maxretailprice', 'maxprice'],
            'description'      => ['description', 'desc', 'longdescription', 'notes'],
            'short_description'=> ['shortdescription', 'shortdesc', 'summary'],
            'track_stock'      => ['trackstock', 'inventoryon', 'stocktracked'],
            'sold_by_weight'   => ['soldbyweight', 'weighed', 'byweight'],
            'track_batches'    => ['trackbatches', 'batchtracked'],
            'track_expiry'     => ['trackexpiry', 'expirytracked'],
            'reorder_level'    => ['reorderlevel', 'minstock', 'lowstockthreshold'],
            'reorder_quantity' => ['reorderquantity', 'reorderqty'],
            'hsn_code'         => ['hsn', 'hsncode', 'taxcode'],
            'pharmacy_schedule'=> ['schedule', 'drugschedule', 'pharmacyschedule'],
            'generic_name'     => ['generic', 'genericname', 'molecule'],
            'manufacturer'     => ['manufacturer', 'maker', 'pharma'],
            'is_active'        => ['active', 'enabled', 'available'],
            'is_featured'      => ['featured', 'highlight'],
            'image'            => ['image', 'imagefile', 'imagename', 'photo', 'picture', 'img'],
        ];

        foreach ($aliases as $field => $candidates) {
            if (in_array($needle, $candidates, true)) {
                return $field;
            }
        }

        return null;
    }

    /** Strip non-alphanumerics + lowercase. "Selling Price" ⇒ "sellingprice". */
    private function canonical(string $s): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($s)) ?: '';
    }

    /**
     * Coerce the wizard's mapping (column index keys may be strings, or
     * the UI may send keys like "1"/"2") into a plain int-keyed array
     * of target fields, with null entries (= "skip this column") dropped.
     *
     * @param  array<int|string, ?string> $mapping
     * @return array<int, string>
     */
    private function normalizeMapping(array $mapping): array
    {
        $out = [];
        foreach ($mapping as $idx => $target) {
            if ($target === null || $target === '') continue;
            if (! in_array($target, self::TARGET_FIELDS, true)) continue;
            $out[(int) $idx] = $target;
        }
        return $out;
    }

    /**
     * Cache lookup tables for category / brand / unit / tax — building
     * them once per import lets us match by name without N queries per
     * row.
     *
     * @return array{categories: array<string,int>, brands: array<string,int>, units: array<string,int>, taxes: array<string,int>}
     */
    private function loadLookups(): array
    {
        return [
            'categories' => Category::query()->pluck('id', 'name')
                ->mapWithKeys(fn ($id, $name) => [strtolower((string) $name) => (int) $id])
                ->all(),
            'brands'     => Brand::query()->pluck('id', 'name')
                ->mapWithKeys(fn ($id, $name) => [strtolower((string) $name) => (int) $id])
                ->all(),
            // Units are matched by code first, then name — a column like
            // "kg" should resolve even if the unit's display name is
            // "Kilogram".
            'units_code' => Unit::query()->pluck('id', 'code')
                ->mapWithKeys(fn ($id, $code) => [strtolower((string) $code) => (int) $id])
                ->all(),
            'units_name' => Unit::query()->pluck('id', 'name')
                ->mapWithKeys(fn ($id, $name) => [strtolower((string) $name) => (int) $id])
                ->all(),
            'taxes'      => TaxGroup::query()->pluck('id', 'name')
                ->mapWithKeys(fn ($id, $name) => [strtolower((string) $name) => (int) $id])
                ->all(),
        ];
    }

    /**
     * Translate a raw spreadsheet row into a product attributes array.
     * Records lookup misses + missing-required errors in $errors and
     * returns null when the row can't be salvaged.
     *
     * @param  array<int, string>                                                                                                          $cells
     * @param  array<int, string>                                                                                                          $mapping
     * @param  array{categories: array<string,int>, brands: array<string,int>, units_code: array<string,int>, units_name: array<string,int>, taxes: array<string,int>} $lookups
     * @param  array<int, array{row:int, message:string}>                                                                                  $errors
     */
    private function buildPayload(array $cells, array $mapping, array $lookups, array &$errors, int $rowNum): ?array
    {
        $get = fn (string $field) => $this->valueFor($cells, $mapping, $field);

        $sku = trim((string) $get('sku'));
        if ($sku === '') {
            // Blank-SKU rows are silently skipped — trailing blank rows
            // are very common in hand-edited spreadsheets.
            return null;
        }

        $payload = [
            'sku'               => $sku,
            'name'              => $get('name'),
            'barcode'           => $this->nullableString($get('barcode')),
            'description'       => $this->nullableString($get('description')),
            'short_description' => $this->nullableString($get('short_description')),
            'cost_price'        => $this->numeric($get('cost_price')),
            'selling_price'     => $this->numeric($get('selling_price')),
            'sale_price'        => $this->numeric($get('sale_price')),
            'mrp'               => $this->numeric($get('mrp')),
            'reorder_level'     => $this->numeric($get('reorder_level')),
            'reorder_quantity'  => $this->numeric($get('reorder_quantity')),
            'hsn_code'          => $this->nullableString($get('hsn_code')),
            'pharmacy_schedule' => $this->nullableString($get('pharmacy_schedule')),
            'generic_name'      => $this->nullableString($get('generic_name')),
            'manufacturer'      => $this->nullableString($get('manufacturer')),
            'track_stock'       => $this->boolish($get('track_stock'), true),
            'sold_by_weight'    => $this->boolish($get('sold_by_weight'), false),
            'track_batches'     => $this->boolish($get('track_batches'), false),
            'track_expiry'      => $this->boolish($get('track_expiry'), false),
            'is_active'         => $this->boolish($get('is_active'), true),
            'is_featured'       => $this->boolish($get('is_featured'), false),
        ];

        // Declared image filename → the Bulk images screen matches an uploaded
        // file against this. Basename-only so a stray path in the cell
        // ("images/apple.jpg") still matches the file "apple.jpg". Only written
        // when the column is actually mapped, so a later prices-only re-import
        // (no image column) doesn't wipe pending refs on existing products.
        if (in_array('image', $mapping, true)) {
            $payload['image_ref'] = $this->imageRef($get('image'));
        }

        // FK lookups by name.
        $payload['category_id']  = $this->resolveLookup($get('category'),  $lookups['categories'], 'category',  $errors, $rowNum);
        $payload['brand_id']     = $this->resolveLookup($get('brand'),     $lookups['brands'],     'brand',     $errors, $rowNum);
        $payload['tax_group_id'] = $this->resolveLookup($get('tax_group'), $lookups['taxes'],      'tax_group', $errors, $rowNum);

        // Unit — try code then name.
        $unitRaw = trim((string) $get('unit'));
        if ($unitRaw !== '') {
            $key = strtolower($unitRaw);
            $payload['unit_id'] = $lookups['units_code'][$key]
                ?? $lookups['units_name'][$key]
                ?? null;
            if ($payload['unit_id'] === null) {
                $errors[] = ['row' => $rowNum, 'message' => "Unknown unit '{$unitRaw}'."];
            }
        }

        return $payload;
    }

    /**
     * Validate a row's normalized payload using the same shape as the
     * editor's Form Request (minus the variant / kit / image rules).
     */
    private function validateRow(array $payload, ?Product $existing): \Illuminate\Contracts\Validation\Validator
    {
        $rules = [
            'sku'           => ['required', 'string', 'max:64'],
            'name'          => ['required', 'string', 'max:191'],
            // Barcode is unique across products. Ignore the row's own
            // product (SKU-matched update) and skip soft-deleted rows —
            // a deleted product's barcode is reclaimable, and the trait
            // below tombstones it before the write. Without this, a clash
            // raised a raw 1062 mid-import instead of a skippable error.
            'barcode'       => [
                'nullable', 'string', 'max:64',
                Rule::unique('products', 'barcode')
                    ->ignore($existing?->id)
                    ->whereNull('deleted_at'),
            ],
            // Category is required, matching the editor's Form Request —
            // every product belongs to a department.
            'category_id'   => ['required', 'integer'],
            'unit_id'       => ['required', 'integer'],
            'cost_price'    => ['required', 'numeric', 'gt:0', 'lte:selling_price', 'max:9999999999.9999'],
            'selling_price' => ['required', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'sale_price'    => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999', 'lte:selling_price'],
            'mrp'           => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999', 'gte:selling_price'],
        ];

        // On existing rows, allow the row to omit columns the user
        // didn't include in the mapping — fall back to the current
        // product values so we don't fail validation just because the
        // CSV is narrower than the products table.
        if ($existing) {
            foreach (['name', 'category_id', 'unit_id', 'cost_price', 'selling_price'] as $col) {
                if ($payload[$col] === null || $payload[$col] === '') {
                    $payload[$col] = $existing->{$col};
                }
            }
        }

        // Friendlier attribute names in the row-error summary.
        return Validator::make($payload, $rules, [], [
            'category_id' => 'category',
            'unit_id'     => 'unit',
        ]);
    }

    private function valueFor(array $cells, array $mapping, string $field): ?string
    {
        $index = array_search($field, $mapping, true);
        if ($index === false) return null;
        return $cells[$index] ?? null;
    }

    private function nullableString(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);
        return ($v === null || $v === '') ? null : $v;
    }

    /**
     * Normalize a declared image reference to a bare filename. The Bulk images
     * screen matches on the file's own name, so a cell holding a path
     * ("images/apple.jpg") or a URL ("https://…/apple.jpg") both reduce to
     * "apple.jpg". A blank cell leaves image_ref null.
     */
    private function imageRef(?string $v): ?string
    {
        $v = $this->nullableString($v);
        if ($v === null) {
            return null;
        }

        // Strip any query string a URL might carry, then take the basename.
        $v = preg_replace('/[?#].*$/', '', $v) ?? $v;
        $base = basename(str_replace('\\', '/', $v));

        return $base === '' ? null : mb_substr($base, 0, 255);
    }

    private function numeric(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);
        if ($v === null || $v === '') return null;
        // Strip thousands separators ("1,234.50" → "1234.50"). We can't
        // safely strip *all* commas in locales that use comma as the
        // decimal separator — but our v1 audience is English-locale and
        // OpenSpout already normalizes XLSX numbers.
        $v = str_replace(',', '', $v);
        return is_numeric($v) ? $v : null;
    }

    private function boolish(?string $v, bool $default): bool
    {
        if ($v === null) return $default;
        $v = strtolower(trim($v));
        if ($v === '') return $default;
        return in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function resolveLookup(?string $raw, array $byName, string $field, array &$errors, int $rowNum): ?int
    {
        $raw = $raw === null ? '' : trim($raw);
        if ($raw === '') return null;
        $key = strtolower($raw);
        if (! isset($byName[$key])) {
            $errors[] = ['row' => $rowNum, 'message' => "Unknown {$field} '{$raw}'."];
            return null;
        }
        return $byName[$key];
    }

    /**
     * Free a SKU / barcode still held by a soft-deleted product so a fresh
     * insert (or rename) doesn't collide with the all-rows unique index.
     * The trashed row is kept (it may be referenced by stock/purchase
     * history) but its identifiers get a `__del<id>` marker. Inlined here
     * (rather than the shared trait) so the importer is a single, fully
     * self-contained file to deploy.
     */
    private function reclaimSoftDeletedIdentifiers(?string $sku, ?string $barcode, ?int $exceptId = null): void
    {
        $sku     = ($sku !== null && $sku !== '') ? $sku : null;
        $barcode = ($barcode !== null && $barcode !== '') ? $barcode : null;

        if ($sku === null && $barcode === null) {
            return;
        }

        $trashed = Product::onlyTrashed()
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where(function ($q) use ($sku, $barcode) {
                if ($sku !== null) {
                    $q->orWhere('sku', $sku);
                }
                if ($barcode !== null) {
                    $q->orWhere('barcode', $barcode);
                }
            })
            ->get();

        foreach ($trashed as $dead) {
            $suffix = '__del'.$dead->id;
            $dead->forceFill([
                'sku'     => mb_substr((string) $dead->sku, 0, 64 - strlen($suffix)).$suffix,
                'barcode' => $dead->barcode !== null
                    ? mb_substr((string) $dead->barcode, 0, 64 - strlen($suffix)).$suffix
                    : null,
            ])->saveQuietly();

            // Same reasoning as ReclaimsSoftDeletedIdentifiers::reclaim…()
            // — free the dead product's extra/alternate barcodes too
            // (see App\Models\ProductBarcode), or they'd keep blocking a
            // fresh import from reusing them.
            $dead->barcodes()->delete();
        }
    }
}
