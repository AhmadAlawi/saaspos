<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\ResolveProductPrice;
use App\Actions\Purchases\CancelPurchase;
use App\Actions\Purchases\CreatePurchase;
use App\Actions\Purchases\DeletePurchase;
use App\Actions\Purchases\ExportPurchases;
use App\Actions\Purchases\ReceivePurchase;
use App\Actions\Purchases\UpdatePurchase;
use App\Exceptions\PurchaseNotEditable;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PurchaseRequest;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\TaxGroup;
use App\Services\Barcodes\ScannedProductResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Purchases admin (Slice 3 — draft → received + cancel).
 *
 * Receive runs stock movements + WAC + supplier outstanding update
 * via {@see ReceivePurchase}. Cancel pre-receive is a clean status
 * flip via {@see CancelPurchase}; cancel post-receive is refused
 * with a flash directing the user to the Return flow (Slice 5).
 *
 * Payment + return flows live in later slices.
 */
class PurchaseController extends Controller
{
    use RendersDataTableRows;
    use RespondsJsonOrRedirect;

    /**
     * Ceiling on line-price lookups per request ({@see prices()}). Well above
     * any realistic PO; it only exists so a crafted `keys[]` can't turn one
     * request into thousands of price resolutions.
     */
    private const MAX_PRICE_KEYS = 300;

    /**
     * Purchases list — server-paginated. Only the first page renders inline;
     * filters (status / supplier / date range / search) and paging round-trip
     * to {@see rows()}. The filter form is Alpine self-managed (reuses the
     * generic `salesIndexPage` factory via the `purchasesIndexPage` alias).
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Purchase::class);

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery($request)->paginate($perPage);
        $summary   = $this->summaryFor($request);

        return view('admin.purchases.index', [
            'purchases'    => $paginator->getCollection(),
            'total'        => $paginator->total(),
            'perPage'      => $perPage,
            'totalPages'   => max(1, $paginator->lastPage()),
            'summaryCards' => $this->summaryCards($summary),
            'suppliers'    => Supplier::query()->active()->orderBy('name')->get(['id', 'name']),
            'filters'      => [
                'q'          => trim((string) $request->query('q', '')),
                'status'     => (string) $request->query('status', 'all'),
                'supplierId' => $request->integer('supplier_id') ?: null,
                'from'       => $request->query('from'),
                'to'         => $request->query('to'),
            ],
        ]);
    }

    /**
     * Resolve a scanned barcode to a product/variant for the purchase editor.
     *
     * Mirrors the inventory editors' scan endpoints — the "variant barcode wins,
     * exact match only" rule lives in {@see ScannedProductResolver}. Gated on
     * `create` since scanning only ever happens while building a draft.
     */
    public function scan(Request $request, ScannedProductResolver $resolver): JsonResponse
    {
        $this->authorize('create', Purchase::class);

        $barcode = trim((string) $request->query('barcode', ''));
        if ($barcode === '') {
            return response()->json(['message' => __('purchases.scan.empty')], 422);
        }

        $row = $resolver->resolve($barcode);
        if ($row === null) {
            return response()->json([
                'message' => __('purchases.scan.not_found', ['barcode' => $barcode]),
            ], 404);
        }

        return response()->json($row);
    }

    /**
     * Read-only price context for the purchase editor's lines: what we sell
     * each item for, its MRP, and the owner's target markup.
     *
     * See docs/features/suppliers-purchases.md §5.7. Key points:
     *
     *  - Nothing here writes prices. A PO is a promise to buy and must not
     *    reprice stock already on the shelf; that stays in ReceivePurchase.
     *  - Selling price and MRP are resolved **for the PO's store** via the
     *    same {@see ResolveProductPrice} cascade the cashier uses, so a shop
     *    with per-store overrides sees the price it will actually ring up.
     *    That also keeps the `product.prices.resolved` filter in play — a
     *    hand-rolled batch query would silently skip plugin price
     *    adjustments and disagree with checkout.
     *  - `markup_percent` is product-level only; it is not store-scoped.
     *
     * Keys are `"{product_id}-{variant_id}"`, with `0` for "no variant" —
     * the editor's line identity. Unknown keys are simply absent from the
     * response; the client renders nothing for them.
     */
    public function prices(Request $request, ResolveProductPrice $resolve): JsonResponse
    {
        $this->authorize('viewAny', Purchase::class);

        $storeId = $request->integer('store_id') ?: null;
        $keys    = array_slice((array) $request->query('keys', []), 0, self::MAX_PRICE_KEYS);

        // Parse + group by product so we load each product once, however
        // many of its variants are on the PO.
        $wanted = [];
        foreach ($keys as $key) {
            if (! is_string($key) || ! preg_match('/^(\d+)-(\d+)$/', $key, $m)) {
                continue;
            }
            $wanted[(int) $m[1]][] = (int) $m[2];
        }

        if ($wanted === []) {
            return response()->json([]);
        }

        $products = Product::query()
            ->whereIn('id', array_keys($wanted))
            ->with('variants:id,product_id,selling_price,mrp,cost_price')
            ->get(['id', 'cost_price', 'selling_price', 'mrp', 'markup_percent']);

        $out = [];
        foreach ($products as $product) {
            foreach ($wanted[$product->id] as $variantId) {
                $variant = $variantId
                    ? $product->variants->firstWhere('id', $variantId)
                    : null;

                // A variant id that isn't this product's — skip rather than
                // silently resolving the parent's price under that key.
                if ($variantId && ! $variant) {
                    continue;
                }

                $resolved = $resolve($product, $storeId, $variant);

                $out[$product->id.'-'.$variantId] = [
                    'selling_price'  => $resolved['selling_price'],
                    'mrp'            => $resolved['mrp'],
                    'markup_percent' => $product->markup_percent !== null
                        ? (string) $product->markup_percent
                        : null,
                ];
            }
        }

        return response()->json($out);
    }

    /** One page of purchase rows as an HTML fragment plus summary meta. */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Purchase::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.purchases._rows', 'purchases', [
            'summary' => $this->summaryFor($request),
        ]);
    }

    /**
     * Active filter set. Store scoping follows the StockMovement / StockLevel
     * convention — the topbar switcher picks which store's POs show unless
     * `?store_id=` overrides. Shared by the row query and the summary aggregate.
     *
     * @return Builder<Purchase>
     */
    private function filteredBase(Request $request): Builder
    {
        $q          = trim((string) $request->query('q', ''));
        $status     = (string) $request->query('status', 'all');
        $supplierId = $request->integer('supplier_id') ?: null;
        $storeId    = enforce_store_access($request->integer('store_id') ?: current_store_id());
        $from       = $request->query('from');
        $to         = $request->query('to');

        return Purchase::query()
            ->when($status !== 'all', fn ($qb) => $qb->where('status', $status))
            ->when($storeId, fn ($qb) => $qb->where('store_id', $storeId))
            ->when($supplierId, fn ($qb) => $qb->where('supplier_id', $supplierId))
            ->when($from, fn ($qb) => $qb->whereDate('purchase_date', '>=', $from))
            ->when($to,   fn ($qb) => $qb->whereDate('purchase_date', '<=', $to))
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('number', 'like', "%{$q}%")
                      ->orWhere('supplier_invoice_number', 'like', "%{$q}%")
                      ->orWhereHas('supplier', fn ($s) => $s
                          ->where('name', 'like', "%{$q}%")
                          ->orWhere('code', 'like', "%{$q}%"));
                });
            });
    }

    /** @return Builder<Purchase> */
    private function listQuery(Request $request): Builder
    {
        return $this->filteredBase($request)
            ->with(['supplier:id,code,name', 'store:id,code,name'])
            ->withCount('attachments')
            ->orderByDesc('id');
    }

    /**
     * Summary-card totals over the FULL filtered set, as display-ready strings.
     *
     * @return array{total:string, count:string, paid:string, outstanding:string}
     */
    private function summaryFor(Request $request): array
    {
        $agg = $this->filteredBase($request)
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total')
            ->selectRaw('COALESCE(SUM(paid_total), 0) as paid')
            ->selectRaw('COALESCE(SUM(balance_due), 0) as outstanding')
            ->first();

        return [
            'total'       => format_money($agg->total ?? 0),
            'count'       => number_format((int) ($agg->cnt ?? 0)),
            'paid'        => format_money($agg->paid ?? 0),
            'outstanding' => format_money($agg->outstanding ?? 0),
        ];
    }

    /**
     * @param  array{total:string, count:string, paid:string, outstanding:string}  $summary
     * @return array<int, array<string, mixed>>
     */
    private function summaryCards(array $summary): array
    {
        return [
            ['label' => __('purchases.summary.total'),       'value' => $summary['total'],       'key' => 'total'],
            ['label' => __('purchases.summary.count'),       'value' => $summary['count'],       'key' => 'count'],
            ['label' => __('purchases.summary.paid'),        'value' => $summary['paid'],        'key' => 'paid', 'tone' => 'positive'],
            ['label' => __('purchases.summary.outstanding'), 'value' => $summary['outstanding'], 'key' => 'outstanding', 'tone' => 'warning'],
        ];
    }

    public function create(): View
    {
        $this->authorize('create', Purchase::class);

        $purchase = new Purchase([
            'status'                => Purchase::STATUS_DRAFT,
            'purchase_date'         => now()->toDateString(),
            'exchange_rate_to_base' => 1,
        ]);
        $purchase->setRelation('items', collect());

        return view('admin.purchases.edit', $this->editorPayload($purchase));
    }

    public function store(PurchaseRequest $request, CreatePurchase $create): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Purchase::class);

        $purchase = $create($request->headerData(), $request->itemData(), $request->user());

        $this->syncAttachment($purchase, $request);

        return $this->jsonOrRedirect(
            $request,
            __('purchases.flash.created', ['number' => $purchase->number]),
            route('admin.purchases.index'),
        );
    }

    public function show(Purchase $purchase): View
    {
        $this->authorize('view', $purchase);

        // `product_variants` has no `name` column — the variant's
        // label is derived from `attributes` JSON via the `label`
        // accessor, so we load `id,attributes` (not `id,name`).
        // Also pull `markup_percent` so the receive button can decide
        // whether to render the per-PO "Update selling prices" checkbox.
        $purchase->load(['supplier', 'store', 'items.product:id,name,sku,markup_percent', 'items.variant:id,sku,attributes', 'items.taxGroup', 'creator:id,name', 'returns', 'attachments']);

        // Markup-eligibility — true when at least one line's product
        // has a non-null `markup_percent`. Drives whether the receive
        // confirm dialog shows the per-PO checkbox.
        $hasMarkupEligibleLines = $purchase->items
            ->contains(fn ($item) => $item->product?->markup_percent !== null);

        // Default state of the per-PO checkbox = company-level default.
        $companyMarkupDefault = (bool) (\App\Models\Company::current()?->auto_apply_markup_on_receive ?? false);

        return view('admin.purchases.show', [
            'purchase'               => $purchase,
            'hasMarkupEligibleLines' => $hasMarkupEligibleLines,
            'companyMarkupDefault'   => $companyMarkupDefault,
            // Lines on this PO whose product is currently oversold (negative
            // on-hand) in the PO's store — receiving nets the backorder off
            // first. Drives the heads-up banner beside the Receive button.
            'oversoldLines'          => $this->oversoldLinesFor($purchase),
        ]);
    }

    /**
     * Items on the purchase whose stock is currently negative in the PO's
     * store, with how much each is oversold by. Empty when nothing on the
     * order is oversold.
     *
     * @return array<int, array{name: string, oversold_by: float}>
     */
    private function oversoldLinesFor(Purchase $purchase): array
    {
        $levels = \App\Models\StockLevel::query()
            ->where('store_id', $purchase->store_id)
            ->where('quantity', '<', 0)
            ->get(['product_id', 'variant_id', 'quantity'])
            ->keyBy(fn ($l) => $l->product_id.':'.($l->variant_id ?? ''));

        $out = [];
        foreach ($purchase->items as $item) {
            $level = $levels->get($item->product_id.':'.($item->variant_id ?? ''));
            if ($level !== null) {
                $out[] = [
                    'name'        => (string) ($item->product?->name ?? ''),
                    'oversold_by' => -(float) $level->quantity,
                ];
            }
        }

        return $out;
    }

    public function edit(Purchase $purchase): View|RedirectResponse
    {
        $this->authorize('update', $purchase);

        if ($purchase->status !== Purchase::STATUS_DRAFT) {
            return redirect()
                ->route('admin.purchases.show', $purchase)
                ->with('error', __('purchases.errors.not_editable', ['status' => __('purchases.status.'.$purchase->status)]));
        }

        $purchase->load('items.product:id,name,sku', 'items.variant:id,attributes', 'attachments');

        return view('admin.purchases.edit', $this->editorPayload($purchase));
    }

    public function update(PurchaseRequest $request, Purchase $purchase, UpdatePurchase $update): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $purchase);

        try {
            $update($purchase, $request->headerData(), $request->itemData(), $request->user());
        } catch (PurchaseNotEditable $e) {
            return $this->jsonOrError(
                $request,
                __('purchases.errors.not_editable', ['status' => __('purchases.status.'.$e->status)]),
                route('admin.purchases.show', $purchase),
            );
        }

        $this->syncAttachment($purchase, $request);

        return $this->jsonOrRedirect(
            $request,
            __('purchases.flash.updated', ['number' => $purchase->number]),
            route('admin.purchases.index'),
        );
    }

    /**
     * Replace / remove the purchase's single invoice attachment.
     * A new upload supersedes any existing file; `remove_attachment`
     * clears it. Files live on the private `local` disk.
     */
    private function syncAttachment(Purchase $purchase, PurchaseRequest $request): void
    {
        if ($request->boolean('remove_attachment') || $request->hasFile('attachment')) {
            foreach ($purchase->attachments()->get() as $existing) {
                Storage::disk('local')->delete($existing->path);
                $existing->delete();
            }
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('purchase-attachments/'.$purchase->id, 'local');

            $purchase->attachments()->create([
                'path'              => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type'         => $file->getClientMimeType(),
                'size_bytes'        => $file->getSize(),
                'uploaded_by'       => $request->user()?->id,
            ]);
        }
    }

    /** Stream a purchase attachment to the browser (auth-gated). */
    public function downloadAttachment(Purchase $purchase, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $purchase);

        abort_unless(
            $attachment->attachable_type === $purchase->getMorphClass()
                && (int) $attachment->attachable_id === (int) $purchase->id,
            404,
        );
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_filename);
    }

    /**
     * Receive a draft — runs stock + WAC + supplier balance update
     * atomically. Idempotent via the DB-level status check inside the
     * action (a second click while the first request is in flight
     * just lands on the friendly flash).
     */
    public function receive(Purchase $purchase, ReceivePurchase $receive, Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('receive', $purchase);

        // Per-PO override for the markup auto-apply behaviour. Sent
        // by the receive button when the purchase has at least one
        // markup-managed line; absent → action falls back to the
        // company-level default.
        $applyMarkup = $request->has('apply_markup')
            ? $request->boolean('apply_markup')
            : null;

        try {
            $receive($purchase, $request->user(), $applyMarkup);
        } catch (PurchaseNotEditable $e) {
            return $this->jsonOrError(
                $request,
                __('purchases.errors.not_receivable', ['status' => __('purchases.status.'.$e->status)]),
                route('admin.purchases.show', $purchase),
            );
        }

        // Markup auto-apply may have bumped selling prices. Flash the
        // per-SKU change list so the show page can render a callout.
        $extra = [];
        if (! empty($receive->priceChanges)) {
            session()->flash('price_changes', $receive->priceChanges);
            $extra['price_changes'] = $receive->priceChanges;
        }

        return $this->jsonOrRedirect(
            $request,
            __('purchases.flash.received', ['number' => $purchase->number]),
            route('admin.purchases.show', $purchase),
            $extra,
        );
    }

    public function cancel(Purchase $purchase, CancelPurchase $cancel, Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $purchase);

        try {
            $cancel($purchase, $request->user(), $request->input('reason'));
        } catch (PurchaseNotEditable $e) {
            return $this->jsonOrError(
                $request,
                __('purchases.errors.not_cancellable', ['status' => __('purchases.status.'.$e->status)]),
                route('admin.purchases.show', $purchase),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('purchases.flash.cancelled', ['number' => $purchase->number]),
            route('admin.purchases.index'),
        );
    }

    public function destroy(Request $request, Purchase $purchase, DeletePurchase $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $purchase);

        $number = $purchase->number;
        try {
            $delete($purchase);
        } catch (PurchaseNotEditable $e) {
            return $this->jsonOrError(
                $request,
                __('purchases.errors.not_deletable', ['status' => __('purchases.status.'.$e->status)]),
                route('admin.purchases.show', $purchase),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('purchases.flash.deleted', ['number' => $number]),
            route('admin.purchases.index'),
        );
    }

    public function export(Request $request, ExportPurchases $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', Purchase::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    /** @return array<string, mixed> */
    private function editorPayload(Purchase $purchase): array
    {
        return [
            'purchase'     => $purchase,
            'suppliers'    => Supplier::query()->active()->orderBy('name')->get(['id', 'code', 'name', 'default_currency_code', 'payment_terms_days']),
            'stores'       => accessible_stores(),
            'taxGroups'    => TaxGroup::query()->with('components:id,name,rate')->orderBy('name')->get(['id', 'name']),
            'baseCurrency' => Company::current()?->base_currency_code ?? 'USD',
            'currencies'   => \Illuminate\Support\Facades\DB::table('currencies')->orderBy('code')->get(['code', 'name', 'symbol']),
        ];
    }
}
