<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\DeleteCustomer;
use App\Actions\Customers\ExportCustomers;
use App\Actions\Customers\UpdateCustomer;
use App\Exceptions\CustomerHasBalance;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerRequest;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customers admin — full CRUD per `docs/features/customers.md` §6–7.
 * The feature-rich tabs (Statement, Credit, Loyalty, Activity) are
 * deferred to post-Sales slices; the Show page here surfaces what we
 * can show today (header + KPIs at zero + addresses + notes).
 */
class CustomerController extends Controller
{
    use RendersDataTableRows;
    use RespondsJsonOrRedirect;

    /**
     * Sortable table columns → their SQL column. Keys match the `sortBy()`
     * arguments on the table headers and the `dt-toolbar-actions` sort menu.
     * Anything else falls back to the default (newest first) in {@see applySort()}.
     */
    private const SORT_COLUMNS = [
        'name'        => 'name',
        'code'        => 'code',
        'outstanding' => 'outstanding_balance',
        'credit'      => 'store_credit_balance',
        'status'      => 'is_active',
        'id'          => 'id',
    ];

    /**
     * Typeahead source for the `remoteSelect` customer picker (e.g. the
     * Sales History customer filter). Returns `[{value, label}]`, matching
     * the products.search contract. Searches name / phone / code.
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $q = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->when($q !== '', fn ($qb) => $qb->where(fn ($w) =>
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%")
                  ->orWhere('code', 'like', "%{$q}%")
            ))
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'phone', 'code']);

        return response()->json(
            $customers->map(fn (Customer $c) => [
                'value' => (string) $c->id,
                'label' => $c->name.($c->phone ? ' · '.$c->phone : ($c->code ? ' · '.$c->code : '')),
            ])->all()
        );
    }

    /**
     * Customers list — server-paginated.
     *
     * Only the first page is rendered inline; every search, filter, sort and
     * page round-trips to {@see rows()}. `customersIndexPage` boots with the
     * filters cleared, so the inline page is deliberately unfiltered and
     * default-sorted (newest first) — only `per_page` is read off the request.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $perPage  = $this->dtPerPage($request);
        $defaults = new Request(['per_page' => $perPage]);

        $paginator = $this->listQuery($defaults)->paginate($perPage);
        $summary   = $this->summaryFor($defaults);

        return view('admin.customers.index', [
            'customers'    => $paginator->getCollection(),
            'perPage'      => $perPage,
            // Unfiltered here, so it doubles as the "N of GRAND" toolbar total
            // and the "you have no customers at all" empty-state check.
            'total'        => $paginator->total(),
            'totalPages'   => max(1, $paginator->lastPage()),
            'summary'      => $summary,
            'summaryCards' => $this->summaryCards($summary),
            'groups'       => CustomerGroup::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * One page of customer rows as an HTML fragment, plus the pager + summary
     * metadata the `dataTableServer` mixin needs. See {@see RendersDataTableRows}.
     */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.customers._rows', 'customers', [
            'summary' => $this->summaryFor($request),
        ]);
    }

    /**
     * Active search + filter set, with no eager loads and no ordering. Shared by
     * the row query and the summary aggregate so the cards always describe the
     * exact set on screen.
     *
     * @return Builder<Customer>
     */
    private function filteredBase(Request $request): Builder
    {
        $query = Customer::query();

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('business_name', 'like', "%{$q}%"));
        }

        $status = (string) $request->query('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $groupId = (int) $request->query('group_id', 0);
        if ($groupId > 0) {
            $query->where('customer_group_id', $groupId);
        }

        return $query;
    }

    /** @return Builder<Customer> */
    private function listQuery(Request $request): Builder
    {
        $query = $this->filteredBase($request)->with('group:id,name');

        $this->applySort($query, $request);

        return $query;
    }

    /** @param Builder<Customer> $query */
    private function applySort(Builder $query, Request $request): void
    {
        $column = (string) $request->query('sort', '');
        $dir    = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (isset(self::SORT_COLUMNS[$column])) {
            $query->orderBy(self::SORT_COLUMNS[$column], $dir);
            // Deterministic tiebreak so rows sharing a sort key can't reshuffle
            // between pages (a customer appearing twice, or never).
            $query->orderBy('id', 'desc');

            return;
        }

        // Default (no active sort): newest first — system-wide DESC default for
        // tables without a manual `sort_order` column (see memory
        // table-and-form-conventions).
        $query->orderByDesc('id');
    }

    /**
     * Summary-card totals honoring the active filters. One aggregate query —
     * never a count() over a hydrated collection. Values arrive display-ready so
     * the JS can drop them straight into the cards on every rows() response.
     *
     * @return array{total:string, with_balance:string, outstanding:string}
     */
    private function summaryFor(Request $request): array
    {
        $row = $this->filteredBase($request)
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN outstanding_balance > 0 THEN 1 ELSE 0 END), 0) AS with_balance_count')
            ->selectRaw('COALESCE(SUM(outstanding_balance), 0) AS outstanding_sum')
            ->first();

        return [
            'total'        => number_format((int) ($row->total_count ?? 0)),
            'with_balance' => number_format((int) ($row->with_balance_count ?? 0)),
            'outstanding'  => format_money($row->outstanding_sum ?? 0),
        ];
    }

    /**
     * `key` ties each card's value to its DOM node so `customersIndexPage` can
     * rewrite it from the `summary` every rows() response carries.
     *
     * @param  array{total:string, with_balance:string, outstanding:string}  $summary
     * @return array<int, array<string, mixed>>
     */
    private function summaryCards(array $summary): array
    {
        return [
            ['label' => __('customers.summary.total'),        'value' => $summary['total'],        'key' => 'total'],
            ['label' => __('customers.summary.with_balance'), 'value' => $summary['with_balance'], 'key' => 'with_balance'],
            ['label' => __('customers.summary.outstanding'),  'value' => $summary['outstanding'],  'key' => 'outstanding', 'tone' => 'warning'],
        ];
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        $customer = new Customer([
            'is_active'   => true,
            'is_business' => false,
        ]);
        $customer->setRelation('addresses', collect());

        return view('admin.customers.edit', $this->editorPayload($customer));
    }

    public function store(CustomerRequest $request, CreateCustomer $create): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $create($request->customerData(), $request->addressData(), $request->user());

        return $this->jsonOrRedirect(
            $request,
            __('customers.flash.created', ['name' => $customer->name]),
            route('admin.customers.index'),
        );
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load(['group', 'firstStore', 'addresses', 'creator:id,name']);

        // Open sales (balance_due > 0, not voided) — drives the
        // "Open sales" drill-down card on the customer page. Sorted
        // oldest first so the most-overdue rises to the top.
        $openSales = \App\Models\Sale::query()
            ->where('customer_id', $customer->id)
            ->whereRaw('balance_due > 0')
            ->where('status', '!=', \App\Models\Sale::STATUS_VOIDED)
            ->orderBy('sale_date')
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'number', 'sale_date', 'currency_code', 'grand_total', 'paid_total', 'balance_due', 'status']);

        return view('admin.customers.show', [
            'customer'  => $customer,
            'openSales' => $openSales,
        ]);
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        $customer->load('addresses');

        return view('admin.customers.edit', $this->editorPayload($customer));
    }

    public function update(CustomerRequest $request, Customer $customer, UpdateCustomer $update): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $customer);

        $update($customer, $request->customerData(), $request->addressData(), $request->user());

        return $this->jsonOrRedirect(
            $request,
            __('customers.flash.updated', ['name' => $customer->name]),
            route('admin.customers.index'),
        );
    }

    /**
     * AJAX flip of `is_active` without opening the edit page. Returns
     * JSON so the row-level toggle on the index can swap state without
     * a full reload. Mirrors Products' `toggleField` pattern.
     */
    public function toggleActive(Customer $customer, Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $customer);

        $customer->update([
            'is_active'  => ! $customer->is_active,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'ok'        => true,
            'is_active' => (bool) $customer->is_active,
            'message'   => __($customer->is_active ? 'customers.flash.activated' : 'customers.flash.deactivated', ['name' => $customer->name]),
        ]);
    }

    public function destroy(Request $request, Customer $customer, DeleteCustomer $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $customer);

        $name = $customer->name;
        try {
            $delete($customer);
        } catch (CustomerHasBalance $e) {
            return $this->jsonOrError(
                $request,
                __('customers.errors.has_balance', ['kind' => $e->kind, 'amount' => number_format($e->amount, 2)]),
                route('admin.customers.show', $customer),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('customers.flash.deleted', ['name' => $name]),
            route('admin.customers.index'),
        );
    }

    public function export(Request $request, ExportCustomers $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', Customer::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    /** @return array<string, mixed> */
    private function editorPayload(Customer $customer): array
    {
        return [
            'customer'      => $customer,
            // Eager-load `default_discount_percent` so the JS form can
            // auto-apply a group's default when the user picks one.
            'groups'        => CustomerGroup::query()->active()->orderBy('name')
                                            ->get(['id', 'name', 'default_discount_percent']),
            'stores'        => Store::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'genders'       => \App\Http\Requests\Admin\CustomerRequest::GENDERS,
            'countries'     => \App\Support\Countries::all(),
            // Default country for new addresses — the company's
            // configured country, falling back to the store's, then 'IN'.
            'defaultCountry' => \App\Models\Company::current()?->country_code
                                ?? Store::query()->value('country_code')
                                ?? 'IN',
        ];
    }
}
