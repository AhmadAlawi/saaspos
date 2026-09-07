<?php

namespace App\Actions\Categories;

use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Models\TaxGroup;
use App\Services\Excel\SpreadsheetReader;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Streaming CSV / XLSX → categories importer — the sibling of
 * {@see \App\Actions\Products\ImportProductsFromCsv}, round-tripping the
 * columns {@see \App\Actions\Categories\ExportCategories} writes.
 *
 * Two modes share one parsing engine:
 *
 *   - `preview(path)`  → headers + first N data rows for the mapping UI.
 *     Side-effect free.
 *   - `commit(path, mapping)` → re-reads the whole file, validates each
 *     row, and creates/updates categories. Returns a summary.
 *
 * Identity: rows match on NAME (case-insensitive) — the category's unique
 * human key. A known name updates that category; a new name creates one.
 *
 * `parent` and `tax_group` are matched by NAME. Parent linking runs in a
 * second pass once every row exists, so a row may reference a parent that
 * is defined *later* in the same file. Cycles (a row parented to itself or
 * a descendant) are rejected per row via {@see Category::wouldCycleIfParentedTo()}.
 *
 * Writes go through {@see CreateCategory}/{@see UpdateCategory} so the
 * category hooks + events (search index, listeners) fire exactly as they
 * do from the editor. `is_default` is never importable (system flag).
 *
 * Hook points:
 *   - filter `categories.import.row`    → adjust a normalized payload pre-validation
 *   - action `categories.before_import` → before the loop opens
 *   - action `categories.after_import`  → after the loop closes, with the summary
 */
class ImportCategoriesFromCsv
{
    /** Importable target fields. Order drives the wizard's mapping dropdown. */
    public const TARGET_FIELDS = [
        'name', 'parent', 'description', 'color', 'tax_group', 'is_active',
    ];

    /** Maximum data rows surfaced in the preview step. */
    public const PREVIEW_ROWS = 10;

    public function __construct(
        private SpreadsheetReader $reader,
        private CreateCategory $createCategory,
        private UpdateCategory $updateCategory,
    ) {}

    /**
     * Side-effect-free preview of an uploaded spreadsheet.
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
            'headers'       => $headers,
            'suggested_map' => $suggested,
            'rows'          => $sample,
            'total_columns' => count($headers),
        ];
    }

    /**
     * Apply the import.
     *
     * @param array<int|string, ?string> $mapping  column-index → target-field
     * @return array{created: int, updated: int, skipped: int, errors: array<int, array{row: int, message: string}>}
     */
    public function commit(string $path, array $mapping, ?int $userId = null): array
    {
        do_action('categories.before_import', $path, $mapping);

        $mapping = $this->normalizeMapping($mapping);
        if (! in_array('name', $mapping, true)) {
            throw new \RuntimeException(__('categories.import.errors.name_required'));
        }

        $lookups = $this->loadLookups();
        // lower(name) → id, seeded with existing categories and extended as
        // new rows are created — the parent pass resolves against this.
        $nameToId = $lookups['categories'];

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];

        // Deferred parent links: [category id, raw parent name, row number].
        $parentLinks = [];

        // 1-based for human-friendly error reporting (row 1 = header).
        $rowNum = 0;

        foreach ($this->reader->rows($path) as $i => $cells) {
            $rowNum++;
            if ($i === 0) continue; // header

            $errorCountBefore = count($errors);
            $payload = $this->buildPayload($cells, $mapping, $lookups, $errors, $rowNum);
            if ($payload === null) {
                // Blank-name row — silently skipped (trailing blanks are common).
                continue;
            }
            if (count($errors) > $errorCountBefore) {
                // A mapped lookup (tax_group / color) missed — hard skip so the
                // data-quality issue surfaces rather than being silently nulled.
                $skipped++;
                continue;
            }

            $payload = apply_filters('categories.import.row', $payload, $cells, $mapping);

            $key      = Str::lower($payload['name']);
            $existing = isset($nameToId[$key]) ? Category::find($nameToId[$key]) : null;

            $validator = $this->validateRow($payload);
            if ($validator->fails()) {
                $errors[] = ['row' => $rowNum, 'message' => $validator->errors()->first()];
                $skipped++;
                continue;
            }

            try {
                $category = DB::transaction(function () use (&$created, &$updated, $existing, $payload) {
                    if ($existing) {
                        // Name is the identity key (unchanged) and slug is left
                        // alone so shared links keep working; parent is set in
                        // the second pass, not here.
                        $this->updateCategory->__invoke($existing, $payload);
                        $updated++;
                        return $existing;
                    }

                    $payload['slug'] = Str::slug($payload['name']).'-'.Str::lower(Str::random(5));
                    $category = $this->createCategory->__invoke($payload);
                    $created++;
                    return $category;
                });
            } catch (QueryException) {
                $errors[] = [
                    'row'     => $rowNum,
                    'message' => __('categories.import.errors.row_failed', ['row' => $rowNum]),
                ];
                $skipped++;
                continue;
            }

            $nameToId[$key] = $category->id;

            $rawParent = trim((string) $this->valueFor($cells, $mapping, 'parent'));
            if ($rawParent !== '') {
                $parentLinks[] = ['id' => $category->id, 'parent' => $rawParent, 'row' => $rowNum];
            }
        }

        $this->linkParents($parentLinks, $nameToId, $errors);

        $summary = [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors'  => $errors,
        ];

        do_action('categories.after_import', $summary);

        return $summary;
    }

    /* ── Internals ─────────────────────────────────────────────── */

    /**
     * Second pass — resolve each deferred parent by name and set it, now
     * that every row exists. Runs one save at a time against the live tree
     * so an incrementally-formed cycle is still caught. Parent problems are
     * reported as row errors; the category itself already imported (it just
     * stays a root until the parent is fixed).
     *
     * @param array<int, array{id:int, parent:string, row:int}>  $parentLinks
     * @param array<string, int>                                 $nameToId
     * @param array<int, array{row:int, message:string}>         $errors
     */
    private function linkParents(array $parentLinks, array $nameToId, array &$errors): void
    {
        foreach ($parentLinks as $link) {
            $key      = Str::lower($link['parent']);
            $parentId = $nameToId[$key] ?? null;

            if ($parentId === null) {
                $errors[] = ['row' => $link['row'], 'message' => __('categories.import.errors.unknown_parent', ['name' => $link['parent']])];
                continue;
            }
            if ($parentId === $link['id']) {
                $errors[] = ['row' => $link['row'], 'message' => __('categories.errors.parent_self')];
                continue;
            }

            $category = Category::find($link['id']);
            if ($category === null) {
                continue;
            }
            if ($category->wouldCycleIfParentedTo($parentId)) {
                $errors[] = ['row' => $link['row'], 'message' => __('categories.errors.parent_cycle')];
                continue;
            }

            $this->updateCategory->__invoke($category, ['parent_id' => $parentId]);
        }
    }

    /**
     * Translate a raw spreadsheet row into a category attributes array.
     * Records lookup / colour misses in $errors; returns null for a
     * blank-name row (nothing to import).
     *
     * @param  array<int, string>                                                    $cells
     * @param  array<int, string>                                                    $mapping
     * @param  array{categories: array<string,int>, taxes: array<string,int>}        $lookups
     * @param  array<int, array{row:int, message:string}>                            $errors
     * @return array<string, mixed>|null
     */
    private function buildPayload(array $cells, array $mapping, array $lookups, array &$errors, int $rowNum): ?array
    {
        $get = fn (string $field) => $this->valueFor($cells, $mapping, $field);

        $name = trim((string) $get('name'));
        if ($name === '') {
            return null;
        }

        $payload = [
            'name'         => $name,
            'description'  => $this->nullableString($get('description')),
            'is_active'    => $this->boolish($get('is_active'), true),
            'tax_group_id' => $this->resolveLookup($get('tax_group'), $lookups['taxes'], 'tax_group', $errors, $rowNum),
            'color'        => $this->resolveColor($get('color'), $errors, $rowNum),
        ];

        return $payload;
    }

    /**
     * Validate a row's normalized payload — the same shape as
     * {@see CategoryRequest} minus the parent rule (parent is resolved in
     * the second pass) and the name-uniqueness rule (a repeated name is an
     * update, not a conflict).
     */
    private function validateRow(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($payload, [
            'name'         => ['required', 'string', 'max:191'],
            'description'  => ['nullable', 'string'],
            'color'        => ['nullable', \Illuminate\Validation\Rule::in(CategoryRequest::COLOR_CHOICES)],
            'tax_group_id' => ['nullable', 'integer'],
            'is_active'    => ['boolean'],
        ], [], [
            'tax_group_id' => 'tax rule',
        ]);
    }

    /**
     * Best-guess mapping for a header string — seeds the UI only; the user
     * can override any suggestion before committing.
     */
    private function suggestField(string $header): ?string
    {
        $needle = $this->canonical($header);

        $aliases = [
            'name'        => ['name', 'category', 'categoryname', 'title'],
            'parent'      => ['parent', 'parentcategory', 'parentname', 'parentcat'],
            'description' => ['description', 'desc', 'notes', 'summary'],
            'color'       => ['color', 'colour', 'accent', 'accentcolor'],
            'tax_group'   => ['tax', 'taxrule', 'taxgroup', 'vat'],
            'is_active'   => ['active', 'enabled', 'status', 'isactive'],
        ];

        foreach ($aliases as $field => $candidates) {
            if (in_array($needle, $candidates, true)) {
                return $field;
            }
        }

        return null;
    }

    /** Strip non-alphanumerics + lowercase. "Parent Category" ⇒ "parentcategory". */
    private function canonical(string $s): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($s)) ?: '';
    }

    /**
     * Coerce the wizard's mapping into a plain int-keyed array of target
     * fields, dropping null ("skip this column") entries.
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
     * Cache lookup tables (built once per import) so names resolve without
     * N queries per row.
     *
     * @return array{categories: array<string,int>, taxes: array<string,int>}
     */
    private function loadLookups(): array
    {
        return [
            'categories' => Category::query()->pluck('id', 'name')
                ->mapWithKeys(fn ($id, $name) => [strtolower((string) $name) => (int) $id])
                ->all(),
            'taxes'      => TaxGroup::query()->pluck('id', 'name')
                ->mapWithKeys(fn ($id, $name) => [strtolower((string) $name) => (int) $id])
                ->all(),
        ];
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

    private function boolish(?string $v, bool $default): bool
    {
        if ($v === null) return $default;
        $v = strtolower(trim($v));
        if ($v === '') return $default;
        return in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    /**
     * Normalize a colour cell to one of the fixed palette hexes (upper-cased
     * so "#f97316" matches "#F97316"). A blank cell is null; an off-palette
     * value is recorded as a row error so it isn't silently dropped.
     */
    private function resolveColor(?string $raw, array &$errors, int $rowNum): ?string
    {
        $raw = $raw === null ? '' : trim($raw);
        if ($raw === '') return null;

        $hex = strtoupper($raw);
        if (! in_array($hex, CategoryRequest::COLOR_CHOICES, true)) {
            $errors[] = ['row' => $rowNum, 'message' => __('categories.import.errors.unknown_color', ['color' => $raw])];
            return null;
        }
        return $hex;
    }

    private function resolveLookup(?string $raw, array $byName, string $field, array &$errors, int $rowNum): ?int
    {
        $raw = $raw === null ? '' : trim($raw);
        if ($raw === '') return null;
        $key = strtolower($raw);
        if (! isset($byName[$key])) {
            $label = $field === 'tax_group' ? __('categories.import.fields.tax_group') : $field;
            $errors[] = ['row' => $rowNum, 'message' => __('categories.import.errors.unknown_lookup', ['field' => $label, 'value' => $raw])];
            return null;
        }
        return $byName[$key];
    }
}
