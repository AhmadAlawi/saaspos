<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Purchases\RecordSupplierPayment;
use App\Actions\Purchases\VoidSupplierPayment;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierPaymentRequest;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Supplier-payments admin (Slices 4 + 4b — record / void / supplier credit).
 *
 * The actions ({@see RecordSupplierPayment}, {@see VoidSupplierPayment})
 * do the heavy lifting — this controller just routes + shapes the form
 * payload. Unallocated credit is recorded as a `purchase_id=null` row
 * when the typed amount exceeds the per-PO allocations (§7.3).
 */
class SupplierPaymentController extends Controller
{
    use RendersDataTableRows;
    use RespondsJsonOrRedirect;

    /**
     * Supplier payments — server-paginated. Filters (supplier / method / date
     * range / search) + paging round-trip to {@see rows()} via the generic
     * `supplierPaymentsPage` (aliased salesIndexPage) factory.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchasePayment::class);

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery($request)->paginate($perPage);
        $summary   = $this->summaryFor($request);

        return view('admin.supplier-payments.index', [
            'payments'     => $paginator->getCollection(),
            'total'        => $paginator->total(),
            'perPage'      => $perPage,
            'totalPages'   => max(1, $paginator->lastPage()),
            'summaryCards' => $this->summaryCards($summary),
            'suppliers'    => Supplier::query()->active()->orderBy('name')->get(['id', 'name']),
            'methods'      => PaymentMethod::query()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'filters'      => [
                'supplierId'      => $request->integer('supplier_id') ?: null,
                'paymentMethodId' => $request->integer('payment_method_id') ?: null,
                'from'            => $request->query('from'),
                'to'              => $request->query('to'),
                'q'               => trim((string) $request->query('q', '')),
            ],
        ]);
    }

    /** One page of payment rows as an HTML fragment plus summary meta. */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchasePayment::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.supplier-payments._rows', 'payments', [
            'summary' => $this->summaryFor($request),
        ]);
    }

    /**
     * Active filter set. Store scoping follows the purchases-index convention.
     *
     * @return Builder<PurchasePayment>
     */
    private function filteredBase(Request $request): Builder
    {
        $supplierId      = $request->integer('supplier_id') ?: null;
        $paymentMethodId = $request->integer('payment_method_id') ?: null;
        $storeId         = enforce_store_access($request->integer('store_id') ?: current_store_id());
        $from            = $request->query('from');
        $to              = $request->query('to');
        $q               = trim((string) $request->query('q', ''));

        return PurchasePayment::query()
            ->when($storeId, fn ($qb) => $qb->where('store_id', $storeId))
            ->when($supplierId, fn ($qb) => $qb->where('supplier_id', $supplierId))
            ->when($paymentMethodId, fn ($qb) => $qb->where('payment_method_id', $paymentMethodId))
            ->when($from, fn ($qb) => $qb->whereDate('payment_date', '>=', $from))
            ->when($to,   fn ($qb) => $qb->whereDate('payment_date', '<=', $to))
            // Free-text on reference / supplier name+code / linked purchase number.
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('reference', 'like', "%{$q}%")
                      ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
                      ->orWhereHas('purchase', fn ($p) => $p->where('number', 'like', "%{$q}%"));
                });
            });
    }

    /** @return Builder<PurchasePayment> */
    private function listQuery(Request $request): Builder
    {
        return $this->filteredBase($request)
            ->with(['supplier:id,code,name', 'paymentMethod:id,code,name', 'purchase:id,number'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');
    }

    /**
     * Summary-card totals over the FULL filtered set, as display-ready strings.
     *
     * @return array{total:string, count:string, suppliers:string}
     */
    private function summaryFor(Request $request): array
    {
        $agg = $this->filteredBase($request)
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('COUNT(DISTINCT supplier_id) as suppliers')
            ->first();

        return [
            'total'     => format_money($agg->total ?? 0),
            'count'     => number_format((int) ($agg->cnt ?? 0)),
            'suppliers' => number_format((int) ($agg->suppliers ?? 0)),
        ];
    }

    /**
     * @param  array{total:string, count:string, suppliers:string}  $summary
     * @return array<int, array<string, mixed>>
     */
    private function summaryCards(array $summary): array
    {
        return [
            ['label' => __('supplier_payments.summary.total'),     'value' => $summary['total'],     'key' => 'total', 'tone' => 'positive'],
            ['label' => __('supplier_payments.summary.count'),     'value' => $summary['count'],     'key' => 'count'],
            ['label' => __('supplier_payments.summary.suppliers'), 'value' => $summary['suppliers'], 'key' => 'suppliers'],
        ];
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PurchasePayment::class);

        // Recovering from a failed submission? `old('supplier_id')`
        // wins so the picker shows the user's previous pick AND we
        // reload the same supplier's open purchases. Falls back to
        // the query-string for the entry point from a purchase show
        // page ("Record payment" CTA passes ?supplier_id=...).
        $supplierId = (int) (old('supplier_id') ?: $request->integer('supplier_id')) ?: null;
        // Preselect the store when the "Record payment" CTA on a
        // purchase show page passes ?store_id=. Falls back to the
        // topbar's active store so a fresh-tab entry still lands
        // somewhere sensible. `old()` wins on validation re-render.
        $storeId    = enforce_store_access((int) (old('store_id') ?: $request->integer('store_id') ?: current_store_id()) ?: null);
        // `from=purchase` is set by the purchase show page's CTA so
        // the view can lock the supplier picker — the cashier
        // shouldn't be able to accidentally book that payment under
        // a different supplier after arriving via "Record payment".
        $fromPurchase = $request->query('from') === 'purchase';

        $openPurchases = collect();
        if ($supplierId) {
            $openPurchases = Purchase::query()
                ->where('supplier_id', $supplierId)
                ->whereIn('status', [Purchase::STATUS_RECEIVED, Purchase::STATUS_PARTIALLY_PAID])
                ->orderBy('purchase_date')
                ->orderBy('id')
                ->get(['id', 'number', 'purchase_date', 'due_date', 'currency_code', 'grand_total', 'paid_total', 'balance_due'])
                ->map(fn ($p) => $this->shapeOpenPurchase($p));
        }

        // Arrived from the shift's cash-drawer panel? Flag it so the form can
        // tell the cashier a cash payment will come out of the open till.
        $fromShift = $request->query('from') === 'shift';
        $hasOpenShift = $fromShift && $storeId
            ? \App\Models\Shift::openForCashier((int) $storeId, (int) $request->user()->id) !== null
            : false;

        return view('admin.supplier-payments.create', [
            'supplierId'    => $supplierId,
            'storeId'       => $storeId,
            'lockSupplier'  => $fromPurchase && $supplierId !== null,
            'fromShift'     => $fromShift,
            'hasOpenShift'  => $hasOpenShift,
            'suppliers'     => Supplier::query()->active()->orderBy('name')->get(['id', 'name', 'default_currency_code']),
            'stores'        => accessible_stores(),
            'methods'       => PaymentMethod::query()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'requires_reference']),
            'openPurchases' => $openPurchases,
        ]);
    }

    public function store(SupplierPaymentRequest $request, RecordSupplierPayment $record): JsonResponse|RedirectResponse
    {
        $this->authorize('create', PurchasePayment::class);

        try {
            $record($request->headerData(), $request->allocationData(), $request->user());
        } catch (RuntimeException $e) {
            // Axios path expects 422 with a Laravel-shaped error envelope
            // so `lib/http.js`'s interceptor unwraps it into the promise
            // rejection. We synthesise a single-key `errors` map so the
            // page's `flattenErrorMessages()` shows the action's message
            // verbatim. Non-AJAX requests keep the old redirect path so
            // any non-migrated entry point (e.g. tests) still works.
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => ['_action' => [$e->getMessage()]],
                ], 422);
            }
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message'  => __('supplier_payments.flash.created'),
                'redirect' => route('admin.supplier-payments.index'),
            ]);
        }

        return redirect()
            ->route('admin.supplier-payments.index')
            ->with('success', __('supplier_payments.flash.created'));
    }

    /**
     * JSON list of a supplier's open purchases — fed to the create
     * form when the user changes the supplier picker. Returns rows
     * with `id`, `number`, `purchase_date`, `currency_code`, and the
     * three money columns the allocation table needs.
     */
    public function openPurchases(Supplier $supplier): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', PurchasePayment::class);

        $rows = Purchase::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('status', [Purchase::STATUS_RECEIVED, Purchase::STATUS_PARTIALLY_PAID])
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->get(['id', 'number', 'purchase_date', 'due_date', 'currency_code', 'grand_total', 'paid_total', 'balance_due'])
            ->map(fn ($p) => $this->shapeOpenPurchase($p));

        return response()->json($rows);
    }

    /**
     * Flatten a Purchase row for the open-purchases picker — dates as
     * plain `Y-m-d` strings (not Carbon→ISO) so the Alpine form renders
     * them without extra parsing.
     *
     * @return array<string, mixed>
     */
    private function shapeOpenPurchase(Purchase $p): array
    {
        return [
            'id'            => $p->id,
            'number'        => $p->number,
            'purchase_date' => $p->purchase_date?->toDateString(),
            'due_date'      => $p->due_date?->toDateString(),
            'currency_code' => $p->currency_code,
            'grand_total'   => $p->grand_total,
            'paid_total'    => $p->paid_total,
            'balance_due'   => $p->balance_due,
        ];
    }

    public function show(PurchasePayment $supplierPayment): View
    {
        $this->authorize('view', $supplierPayment);

        $supplierPayment->load(['supplier', 'store', 'paymentMethod', 'purchase', 'creator:id,name', 'voider:id,name']);

        return view('admin.supplier-payments.show', [
            'payment' => $supplierPayment,
        ]);
    }

    /**
     * Void a recorded payment — reverses the cash effect on the linked
     * purchase (if any) and the supplier outstanding. The row stays in
     * history with a `voided_at` marker. Surfaces the action's
     * RuntimeException (already-voided, etc.) as a 422 / flash error.
     */
    public function void(Request $request, PurchasePayment $supplierPayment, VoidSupplierPayment $void): JsonResponse|RedirectResponse
    {
        $this->authorize('void', $supplierPayment);

        $data = $request->validate([
            // Optional. Audit-only; the cash math doesn't care.
            'void_reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $void($supplierPayment, $data['void_reason'] ?? null, $request->user());
        } catch (RuntimeException $e) {
            return $this->jsonOrError(
                $request,
                $e->getMessage(),
                route('admin.supplier-payments.show', $supplierPayment),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('supplier_payments.flash.voided'),
            route('admin.supplier-payments.show', $supplierPayment),
        );
    }
}
