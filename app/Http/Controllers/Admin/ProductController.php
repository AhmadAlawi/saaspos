<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\DeleteProduct;
use App\Actions\Products\ExportProducts;
use App\Actions\Products\SyncProductBarcodes;
use App\Actions\Products\SyncProductKitItems;
use App\Actions\Products\SyncProductPrices;
use App\Actions\Products\SyncProductVariants;
use App\Actions\Products\ToggleProductFlag;
use App\Actions\Products\UpdateProduct;
use App\Exceptions\ProductHasSales;
use App\Exceptions\ProductVariantHasSales;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\TaxGroup;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Products module — full-page CRUD.
 *
 * Unlike the master-detail flows used by Categories / Brands / Units,
 * Products has too many fields to fit a side-editor, so create + edit
 * each get their own dedicated page. The list view is plain table-mode
 * data-table with sortable columns + search + pagination.
 *
 * Image upload follows the same shape as Brand:
 *   - `image` (file) → store and overwrite path
 *   - `image_remove=1` → drop the stored file, blank the column
 *   - field absent → leave existing image alone
 */
class ProductController extends Controller
{
    use RespondsJsonOrRedirect;

    /**
     * Columns the table headers may sort on, mapped to their SQL column.
     * `category` and `margin` need expressions and are handled separately
     * in {@see applySort()}. Anything else falls back to `ordered()`.
     */
    private const SORT_COLUMNS = [
        'name'   => 'products.name',
        'sku'    => 'products.sku',
        'type'   => 'products.type',
        'price'  => 'products.selling_price',
        'cost'   => 'products.cost_price',
        'active' => 'products.is_active',
    ];

    private const PRODUCT_TYPES = ['simple', 'variant', 'kit'];

    /** Cards per grid page (the infinite-scroll batch) vs rows per table page. */
    private const GRID_PAGE_SIZE  = 30;
    private const TABLE_PAGE_SIZE = 25;
    private const MAX_PAGE_SIZE   = 100;

    /**
     * Products list — server-paginated.
     *
     * Only the first page of the *active view* is rendered inline; every
     * subsequent page, search, filter and sort round-trips to {@see rows()}.
     * A 7k-SKU catalog previously rendered each product twice (once as a table
     * row, once as a grid card) — ~17 Alpine directives per product in the DOM,
     * which timed the page out.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $view    = $this->resolveView($request);
        $perPage = $this->resolvePerPage($request, $view);

        // The inline first page is deliberately unfiltered and default-sorted:
        // `productsPage` boots with every filter at 'all', and its no-op guard
        // assumes the rows on screen match that state. Search, filters and sort
        // all round-trip through rows(). Only `view` and `per_page` are read
        // off the request here.
        $defaults = new Request(['view' => $view, 'per_page' => $perPage]);

        $products = $this->listQuery($defaults)->paginate($perPage)->getCollection();
        $summary  = $this->summaryFor($defaults);

        return view('admin.products.index', [
            'products'         => $products,
            'view'             => $view,
            'perPage'          => $perPage,
            // The two views page at different sizes. The JS needs both, so
            // toggling grid→table doesn't carry the grid's batch size across.
            'tablePageSize'    => self::TABLE_PAGE_SIZE,
            'gridPageSize'     => self::GRID_PAGE_SIZE,
            // Unfiltered here, so it doubles as the "N of GRAND" toolbar total
            // and the "you have no products at all" empty-state check.
            'grandTotal'       => $summary['total'],
            'summary'          => $summary,
            'summaryCards'     => $this->summaryCards($summary),
            'filterCategories' => Category::query()->active()->ordered()->get(['id', 'name']),
        ]);
    }

    /**
     * One page of product rows (table) or cards (grid) as an HTML fragment,
     * plus the pager + summary metadata the `dataTableServer` mixin needs.
     *
     * Returning rendered Blade rather than JSON records keeps the row markup in
     * exactly one place (`_rows.blade.php` / `_cards.blade.php`) instead of
     * duplicating it as a JS template.
     */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $view      = $this->resolveView($request);
        $perPage   = $this->resolvePerPage($request, $view);
        $page      = max(1, (int) $request->query('page', 1));
        $paginator = $this->listQuery($request)->paginate($perPage, ['*'], 'page', $page);

        $html = view(
            $view === 'table' ? 'admin.products._rows' : 'admin.products._cards',
            ['products' => $paginator->getCollection()],
        )->render();

        return response()->json([
            'html'        => $html,
            'total'       => $paginator->total(),
            'page'        => $paginator->currentPage(),
            'per_page'    => $paginator->perPage(),
            'total_pages' => max(1, $paginator->lastPage()),
            'summary'     => $this->summaryFor($request),
        ]);
    }

    /** 'table' | 'grid' — grid is the default view (matches productsPage). */
    private function resolveView(Request $request): string
    {
        return $request->query('view') === 'table' ? 'table' : 'grid';
    }

    private function resolvePerPage(Request $request, string $view): int
    {
        $default = $view === 'table' ? self::TABLE_PAGE_SIZE : self::GRID_PAGE_SIZE;
        $perPage = (int) $request->query('per_page', $default);

        return max(1, min($perPage, self::MAX_PAGE_SIZE));
    }

    /**
     * The active search + filter set, with no eager loads and no ordering.
     * Shared by the row query and the summary aggregate, so the cards always
     * describe exactly the set the user is looking at.
     *
     * @return Builder<Product>
     */
    private function filteredBase(Request $request): Builder
    {
        $query = Product::query();

        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $query->where(fn (Builder $w) => $w
                ->where('products.name', 'like', "%{$term}%")
                ->orWhere('products.sku', 'like', "%{$term}%")
                ->orWhere('products.barcode', 'like', "%{$term}%")
                // Extra/alternate barcodes (see App\Models\ProductBarcode) —
                // same gap as the cashier scan box and the kit-item picker's
                // search() below; this is the main products list's own filter.
                ->orWhereHas('barcodes', fn ($b) => $b->where('barcode', 'like', "%{$term}%")));
        }

        $category = (string) $request->query('category', 'all');
        if ($category !== 'all' && ctype_digit($category)) {
            $query->where('products.category_id', (int) $category);
        }

        $type = (string) $request->query('type', 'all');
        if (in_array($type, self::PRODUCT_TYPES, true)) {
            $query->where('products.type', $type);
        }

        $status = (string) $request->query('status', 'all');
        if ($status === 'active') {
            $query->where('products.is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('products.is_active', false);
        }

        if ($request->query('featured') === 'featured') {
            $query->where('products.is_featured', true);
        }

        return $query;
    }

    /** @return Builder<Product> */
    private function listQuery(Request $request): Builder
    {
        $query = $this->filteredBase($request)
            ->with([
                'category:id,name',
                'brand:id,name,logo_path',
                'unit:id,code,name',
                'taxGroup:id,name',
            ])
            // Counts drive the "Variant · N" / "Kit · N" type badges.
            ->withCount(['variants', 'kitItems']);

        $this->applySort($query, $request);

        return $query;
    }

    /** @param Builder<Product> $query */
    private function applySort(Builder $query, Request $request): void
    {
        $column = (string) $request->query('sort', '');
        $dir    = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($column === 'category') {
            // Correlated subquery rather than a join — avoids ambiguous columns
            // and leaves the withCount subqueries intact.
            $query->orderBy(
                Category::query()->select('name')->whereColumn('categories.id', 'products.category_id'),
                $dir,
            );
        } elseif ($column === 'margin') {
            // Margin isn't a column; it's (price - cost) / price. NULLIF guards
            // the zero-price divide. `$dir` is whitelisted to asc|desc above.
            $query->orderByRaw(
                "(products.selling_price - products.cost_price) / NULLIF(products.selling_price, 0) {$dir}"
            );
        } elseif (isset(self::SORT_COLUMNS[$column])) {
            $query->orderBy(self::SORT_COLUMNS[$column], $dir);
        } else {
            $query->ordered();

            return;
        }

        // Deterministic tiebreak. Without it, rows sharing a sort key can
        // reshuffle between pages, so a product appears twice — or never.
        $query->orderBy('products.id', 'desc');
    }

    /**
     * Totals for the summary cards, honoring the active filters. One aggregate
     * query — never a `count()` over a hydrated collection.
     *
     * @return array{total:int, active:int, featured:int}
     */
    private function summaryFor(Request $request): array
    {
        $row = $this->filteredBase($request)
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw('COALESCE(SUM(products.is_active), 0) AS active_count')
            ->selectRaw('COALESCE(SUM(products.is_featured), 0) AS featured_count')
            ->first();

        return [
            'total'    => (int) ($row->total_count ?? 0),
            'active'   => (int) ($row->active_count ?? 0),
            'featured' => (int) ($row->featured_count ?? 0),
        ];
    }

    /**
     * `key` ties each card's value to its DOM node so `productsPage` can rewrite
     * it from the `summary` every rows() response carries.
     *
     * @param  array{total:int, active:int, featured:int}  $summary
     */
    private function summaryCards(array $summary): array
    {
        return [
            ['label' => __('products.summary.total'),    'value' => number_format($summary['total']),    'key' => 'total'],
            ['label' => __('products.summary.active'),   'value' => number_format($summary['active']),   'key' => 'active', 'tone' => 'positive'],
            ['label' => __('products.summary.featured'), 'value' => number_format($summary['featured']), 'key' => 'featured'],
        ];
    }

    /**
     * JSON search for the remote product picker (kit components, and any
     * future product-select). Returns at most 25 active products matching
     * the query on name / SKU / barcode, each with its variants so a
     * picker can offer "lock to variant" without a second round-trip.
     *
     * Shape: `[{ value, label, variants: [{id, label}] }]`.
     */
    /**
     * Exact barcode (falling back to SKU) lookup — for the label print
     * wizard's scan-and-Enter flow, distinct from search()'s fuzzy
     * name/sku/barcode LIKE matching. One product or 404, never a list.
     */
    public function lookupBarcode(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $code = trim((string) $request->query('barcode', ''));
        if ($code === '') {
            return response()->json(['message' => 'barcode is required.'], 422);
        }

        $product = Product::query()->active()->where('barcode', $code)->first()
            ?? Product::query()->active()->where('sku', $code)->first()
            // Extra/alternate barcode (see App\Models\ProductBarcode) —
            // last resort, after the product's own primary barcode/SKU.
            ?? Product::query()->active()->whereHas('barcodes', fn ($q) => $q->where('barcode', $code))->first();

        if (! $product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        return response()->json([
            'value' => (string) $product->id,
            'label' => $product->name.' ('.$product->sku.')',
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $q       = trim((string) $request->query('q', ''));
        $exclude = (int) $request->query('exclude', 0);

        $products = Product::query()
            ->active()
            ->when($exclude > 0, fn ($qb) => $qb->where('id', '!=', $exclude))
            ->when($q !== '', fn ($qb) => $qb->where(fn ($w) =>
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('sku', 'like', "%{$q}%")
                  ->orWhere('barcode', 'like', "%{$q}%")
                  ->orWhereHas('barcodes', fn ($w2) => $w2->where('barcode', 'like', "%{$q}%"))
            ))
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'sku', 'track_batches', 'track_expiry', 'cost_price', 'selling_price']);

        $variantsByProduct = ProductVariant::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->orderBy('id')
            ->get(['id', 'product_id', 'sku', 'attributes', 'cost_price', 'selling_price'])
            ->groupBy('product_id');

        return response()->json(
            $products->map(fn (Product $p) => [
                'value'         => (string) $p->id,
                'label'         => $p->name.' ('.$p->sku.')',
                'track_batches' => (bool) $p->track_batches,
                'track_expiry'  => (bool) $p->track_expiry,
                // Prices power the kit editor's auto-pricing (sum of components).
                'cost_price'    => (string) $p->cost_price,
                'selling_price' => (string) $p->selling_price,
                'variants'      => $variantsByProduct->get($p->id, collect())
                    // A variant's own price wins; null falls back to the parent's.
                    ->map(fn (ProductVariant $v) => [
                        'id'            => (string) $v->id,
                        'label'         => (string) $v->label,
                        'cost_price'    => (string) ($v->cost_price    ?? $p->cost_price),
                        'selling_price' => (string) ($v->selling_price ?? $p->selling_price),
                    ])
                    ->values(),
            ])->all()
        );
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('admin.products.create', $this->formContext());
    }

    public function store(ProductRequest $request, CreateProduct $create, SyncProductVariants $syncVariants, SyncProductKitItems $syncKitItems, SyncProductPrices $syncPrices, SyncProductBarcodes $syncBarcodes): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->persistedAttributes();
        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product = ($create)($data);

        // Sync variants. For `simple` / `kit`, the payload is empty
        // — sync becomes a no-op (nothing to delete, nothing to add).
        // For `variant`, we persist whatever the user provided.
        ($syncVariants)(
            $product,
            $product->type === 'variant' ? $request->variantsPayload() : []
        );

        // Sync kit components — mirror shape for `type=kit`.
        ($syncKitItems)(
            $product,
            $product->type === 'kit' ? $request->kitItemsPayload() : []
        );

        // Product-level per-store overrides apply only to simple / kit
        // products. Variant products price per-child, so their store
        // overrides ride along inside SyncProductVariants above.
        if ($product->type !== 'variant') {
            ($syncPrices)($product, $request->storePricesPayload());
        }

        ($syncBarcodes)($product, $request->barcodesPayload());

        return $this->jsonOrRedirect(
            $request,
            __('products.flash.created', ['name' => $product->name]),
            route('admin.products.edit', $product),
        );
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        // Eager-load variants + kit items so the editor renders existing
        // rows without an N+1 problem on first paint. Kit items pull in
        // their component product / variant so the picker can render the
        // current selection by name without a follow-up query.
        $product->load([
            'variants' => fn ($q) => $q->orderBy('id'),
            'kitItems' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'kitItems.component:id,name,sku',
            'kitItems.variant:id,product_id,sku,attributes',
            'prices',
            'barcodes' => fn ($q) => $q->orderBy('id'),
        ]);

        return view('admin.products.edit', array_merge($this->formContext(), [
            'product' => $product,
        ]));
    }

    public function update(ProductRequest $request, Product $product, UpdateProduct $update, SyncProductVariants $syncVariants, SyncProductKitItems $syncKitItems, SyncProductPrices $syncPrices, SyncProductBarcodes $syncBarcodes): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $product);

        $data = $request->persistedAttributes();
        $data['updated_by'] = $request->user()?->id;

        // Three image lifecycles — same as BrandController::update().
        if ($request->hasFile('image')) {
            $this->deleteStoredImage($product);
            $data['image_path'] = $request->file('image')->store('products', 'public');
        } elseif ($request->boolean('image_remove')) {
            $this->deleteStoredImage($product);
            $data['image_path'] = null;
        }

        ($update)($product, $data);

        // Always run sync — payload is the user's variants[] when the
        // product is `type=variant`, empty otherwise. The empty case
        // matters on type transitions away from variant: it wipes any
        // orphaned children left behind. Sales-guard inside sync may
        // refuse if a child variant has historical sale lines.
        $fresh = $product->fresh();
        try {
            ($syncVariants)(
                $fresh,
                $fresh->type === 'variant' ? $request->variantsPayload() : []
            );
        } catch (ProductVariantHasSales $e) {
            return $this->jsonOrError(
                $request,
                __('products.errors.variant_has_sales', [
                    'label' => $e->label,
                    'count' => $e->count,
                ]),
                route('admin.products.edit', $product),
            );
        }

        // Same shape for kit components — empty payload on non-kit
        // types wipes any orphans from a prior `type=kit` state.
        ($syncKitItems)(
            $fresh,
            $fresh->type === 'kit' ? $request->kitItemsPayload() : []
        );

        // Product-level per-store overrides — simple / kit only. Variant
        // products' overrides are written per-child inside the variant
        // sync. On a type-down-switch away from variant, the stale
        // variant rows cascade-delete with their variants.
        if ($fresh->type !== 'variant') {
            ($syncPrices)($fresh, $request->storePricesPayload());
        }

        ($syncBarcodes)($fresh, $request->barcodesPayload());

        // Redirect to the list instead of back to the edit page — the
        // user can immediately scan / pick another product to edit.
        // Successful saves should feel "done", not "still here".
        return $this->jsonOrRedirect(
            $request,
            __('products.flash.updated', ['name' => $product->name]),
            route('admin.products.index'),
        );
    }

    public function destroy(Request $request, Product $product, DeleteProduct $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $product);

        $name = $product->name;

        try {
            ($delete)($product);
        } catch (ProductHasSales $e) {
            return $this->jsonOrError(
                $request,
                __('products.errors.has_sales', ['count' => $e->count, 'name' => $name]),
                route('admin.products.edit', $product),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('products.flash.deleted', ['name' => $name]),
            route('admin.products.index'),
        );
    }

    /**
     * Row-level quick-action: flip a single boolean flag (is_active or
     * is_featured) without running through the full validation
     * pipeline. Returns JSON so the products list page can update
     * optimistically without a full reload.
     */
    public function toggle(Request $request, Product $product, ToggleProductFlag $toggle): JsonResponse
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            'field' => ['required', Rule::in(['is_active', 'is_featured'])],
            'value' => ['required', 'boolean'],
        ]);

        ($toggle)($product, $data['field'], (bool) $data['value']);

        return response()->json([
            'ok'          => true,
            'id'          => $product->id,
            'is_active'   => (bool) $product->is_active,
            'is_featured' => (bool) $product->is_featured,
            'message'     => __('products.flash.updated', ['name' => $product->name]),
        ]);
    }

    public function export(Request $request, ExportProducts $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', Product::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    /* ── Helpers ────────────────────────────────────────────────── */

    /**
     * Shared dropdown data for the create + edit forms. Eager-load just
     * what the option lists need so the form payload stays light.
     *
     * @return array<string, mixed>
     */
    private function formContext(): array
    {
        // Include `tax_group_id` on categories — the form uses this to
        // pre-fill the Tax rule field when the user picks a category
        // that has a default rule configured.
        $categories = Category::query()->active()->ordered()->get(['id', 'name', 'tax_group_id']);

        return [
            'categories'     => $categories,
            'brands'         => Brand::query()->active()->ordered()->get(['id', 'name']),
            'units'          => Unit::query()->active()->ordered()->get(['id', 'code', 'name', 'category']),
            'taxGroups'      => TaxGroup::query()->active()->ordered()->get(['id', 'name']),
            // Per-store price-override UI uses this list. Hidden when
            // empty (single-store installs that haven't yet seeded a
            // store row).
            'stores'         => Store::query()->active()->ordered()->get(['id', 'name', 'code', 'currency_code']),
            // Plain map { category_id (string) → tax_group_id (string|null) }
            // for the editor's JS — categoryId / taxGroupId watcher.
            'categoryTaxMap' => $categories
                ->mapWithKeys(fn ($c) => [(string) $c->id => $c->tax_group_id ? (string) $c->tax_group_id : ''])
                ->all(),
            // Dynamic lookup — managed via /admin/drug-schedules.
            'pharmacySchedules' => \App\Models\DrugSchedule::query()
                ->active()
                ->ordered()
                ->get(['code', 'name', 'country_code']),
        ];
    }

    private function deleteStoredImage(Product $product): void
    {
        if (! $product->image_path) return;
        if (Storage::disk('public')->exists($product->image_path)) {
            Storage::disk('public')->delete($product->image_path);
        }
    }
}
