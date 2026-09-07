<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Suppliers\CreateSupplier;
use App\Actions\Suppliers\DeleteSupplier;
use App\Actions\Suppliers\ExportSuppliers;
use App\Actions\Suppliers\UpdateSupplier;
use App\Exceptions\SupplierHasBalance;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Suppliers admin — CRUD per `docs/features/suppliers-purchases.md`
 * §3. The header KPIs (lifetime spend, outstanding, last order) sit
 * at zero in this slice; Purchases / Payments slices wire the live
 * numbers. The supplier-detail's Purchases/Payments/Returns tabs
 * stay as placeholders until those slices land.
 */
class SupplierController extends Controller
{
    use RendersDataTableRows;
    use RespondsJsonOrRedirect;

    /**
     * Sortable table columns → their SQL column. Keys match the `sortBy()`
     * arguments on the table headers and the `dt-toolbar-actions` sort menu.
     */
    private const SORT_COLUMNS = [
        'name'        => 'name',
        'code'        => 'code',
        'outstanding' => 'outstanding_balance',
        'status'      => 'is_active',
        'id'          => 'id',
    ];

    /**
     * Suppliers list — server-paginated. Only the first page is rendered inline;
     * search, the status filter, sort and paging round-trip to {@see rows()}.
     * The generic `customersIndexPage` factory boots with filters cleared, so the
     * inline page is unfiltered and default-sorted (newest first).
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $perPage  = $this->dtPerPage($request);
        $defaults = new Request(['per_page' => $perPage]);

        $paginator = $this->listQuery($defaults)->paginate($perPage);
        $summary   = $this->summaryFor($defaults);

        return view('admin.suppliers.index', [
            'suppliers'    => $paginator->getCollection(),
            'perPage'      => $perPage,
            'total'        => $paginator->total(),
            'totalPages'   => max(1, $paginator->lastPage()),
            'summaryCards' => $this->summaryCards($summary),
        ]);
    }

    /**
     * One page of supplier rows as an HTML fragment plus pager + summary meta.
     * See {@see RendersDataTableRows}.
     */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.suppliers._rows', 'suppliers', [
            'summary' => $this->summaryFor($request),
        ]);
    }

    /**
     * Active search + filter set, no ordering. Shared by the row query and the
     * summary aggregate so the cards describe the exact set on screen.
     *
     * @return Builder<Supplier>
     */
    private function filteredBase(Request $request): Builder
    {
        $query = Supplier::query();

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('business_name', 'like', "%{$q}%")
                ->orWhere('contact_person', 'like', "%{$q}%"));
        }

        $status = (string) $request->query('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query;
    }

    /** @return Builder<Supplier> */
    private function listQuery(Request $request): Builder
    {
        $query = $this->filteredBase($request);

        $this->applySort($query, $request);

        return $query;
    }

    /** @param Builder<Supplier> $query */
    private function applySort(Builder $query, Request $request): void
    {
        $column = (string) $request->query('sort', '');
        $dir    = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (isset(self::SORT_COLUMNS[$column])) {
            $query->orderBy(self::SORT_COLUMNS[$column], $dir);
            $query->orderBy('id', 'desc');

            return;
        }

        // Default (no active sort): newest suppliers first.
        $query->orderByDesc('id');
    }

    /**
     * Summary-card totals honoring the active filters. One aggregate query,
     * values arriving display-ready for the JS to drop into the cards.
     *
     * @return array{total:string, payable:string, active:string}
     */
    private function summaryFor(Request $request): array
    {
        $row = $this->filteredBase($request)
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw('COALESCE(SUM(outstanding_balance), 0) AS payable_sum')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_count')
            ->first();

        return [
            'total'   => number_format((int) ($row->total_count ?? 0)),
            'payable' => format_money($row->payable_sum ?? 0),
            'active'  => number_format((int) ($row->active_count ?? 0)),
        ];
    }

    /**
     * `key` ties each card's value to its DOM node so the index factory can
     * rewrite it from the `summary` every rows() response carries.
     *
     * @param  array{total:string, payable:string, active:string}  $summary
     * @return array<int, array<string, mixed>>
     */
    private function summaryCards(array $summary): array
    {
        return [
            ['label' => __('suppliers.summary.total'),   'value' => $summary['total'],   'key' => 'total'],
            ['label' => __('suppliers.summary.payable'), 'value' => $summary['payable'], 'key' => 'payable', 'tone' => 'warning'],
            ['label' => __('suppliers.summary.active'),  'value' => $summary['active'],  'key' => 'active', 'tone' => 'positive'],
        ];
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        $supplier = new Supplier([
            'is_active' => true,
        ]);

        return view('admin.suppliers.edit', $this->editorPayload($supplier));
    }

    public function store(SupplierRequest $request, CreateSupplier $create): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = $create($request->supplierData(), $request->user());

        return $this->jsonOrRedirect(
            $request,
            __('suppliers.flash.created', ['name' => $supplier->name]),
            route('admin.suppliers.index'),
        );
    }

    public function show(Supplier $supplier): View
    {
        $this->authorize('view', $supplier);

        $supplier->load('creator:id,name');

        return view('admin.suppliers.show', [
            'supplier' => $supplier,
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('admin.suppliers.edit', $this->editorPayload($supplier));
    }

    public function update(SupplierRequest $request, Supplier $supplier, UpdateSupplier $update): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $supplier);

        $update($supplier, $request->supplierData(), $request->user());

        return $this->jsonOrRedirect(
            $request,
            __('suppliers.flash.updated', ['name' => $supplier->name]),
            route('admin.suppliers.index'),
        );
    }

    /**
     * AJAX flip of `is_active` without opening the edit page. Mirrors
     * the customers + products pattern.
     */
    public function toggleActive(Supplier $supplier, Request $request): JsonResponse
    {
        $this->authorize('update', $supplier);

        $willDeactivate = $supplier->is_active;
        if ($willDeactivate) {
            // Same guard as DeleteSupplier — open purchases keep the
            // supplier "in use", so they can't be silently retired
            // mid-flow. Activating doesn't need this check.
            $openCount = \App\Models\Purchase::query()
                ->where('supplier_id', $supplier->getKey())
                ->whereIn('status', [
                    \App\Models\Purchase::STATUS_DRAFT,
                    \App\Models\Purchase::STATUS_SUBMITTED,
                    \App\Models\Purchase::STATUS_RECEIVED,
                    \App\Models\Purchase::STATUS_PARTIALLY_PAID,
                ])
                ->count();
            if ($openCount > 0) {
                return response()->json([
                    'ok'      => false,
                    'message' => __('suppliers.errors.has_open_purchases', ['name' => $supplier->name, 'count' => $openCount]),
                ], 422);
            }
        }

        $supplier->update([
            'is_active'  => ! $supplier->is_active,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'ok'        => true,
            'is_active' => (bool) $supplier->is_active,
            'message'   => __($supplier->is_active ? 'suppliers.flash.activated' : 'suppliers.flash.deactivated', ['name' => $supplier->name]),
        ]);
    }

    public function destroy(Request $request, Supplier $supplier, DeleteSupplier $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $name = $supplier->name;
        try {
            $delete($supplier);
        } catch (SupplierHasBalance $e) {
            return $this->jsonOrError(
                $request,
                __('suppliers.errors.has_balance', ['amount' => format_money($e->amount)]),
                route('admin.suppliers.show', $supplier),
            );
        } catch (\App\Exceptions\SupplierHasOpenPurchases $e) {
            return $this->jsonOrError(
                $request,
                __('suppliers.errors.has_open_purchases', ['name' => $name, 'count' => $e->count]),
                route('admin.suppliers.show', $supplier),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('suppliers.flash.deleted', ['name' => $name]),
            route('admin.suppliers.index'),
        );
    }

    public function export(Request $request, ExportSuppliers $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    /** @return array<string, mixed> */
    private function editorPayload(Supplier $supplier): array
    {
        return [
            'supplier'       => $supplier,
            'countries'      => \App\Support\Countries::all(),
            'currencies'     => \Illuminate\Support\Facades\DB::table('currencies')
                                    ->orderBy('code')->get(['code', 'name', 'symbol']),
            'defaultCountry' => \App\Models\Company::current()?->country_code
                                ?? \App\Models\Store::query()->value('country_code')
                                ?? 'IN',
            'baseCurrency'   => \App\Models\Company::current()?->base_currency_code ?? 'USD',
        ];
    }
}
