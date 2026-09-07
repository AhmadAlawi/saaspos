<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Sales\RecordCustomerPayments;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerPaymentRequest;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Customer-payments admin (Slice 6b) — record a single payment that
 * allocates across one or more open sales for a customer. Mirror of
 * {@see SupplierPaymentController}.
 *
 * Sale-time tenders (the cashier's "Accept cash" path) still write
 * `sale_payments` rows with `customer_id=null`. These settlement rows
 * carry `customer_id` so the index can isolate them cleanly.
 */
class CustomerPaymentController extends Controller
{
    use RendersDataTableRows;
    use RespondsJsonOrRedirect;

    /**
     * Customer payments — server-paginated. Filters (customer / date range) +
     * paging round-trip to {@see rows()} via the generic `customerPaymentsPage`
     * (aliased salesIndexPage) factory.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalePayment::class);

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery($request)->paginate($perPage);
        $summary   = $this->summaryFor($request);

        return view('admin.customer-payments.index', [
            'payments'     => $paginator->getCollection(),
            'total'        => $paginator->total(),
            'perPage'      => $perPage,
            'totalPages'   => max(1, $paginator->lastPage()),
            'summaryCards' => $this->summaryCards($summary),
            'customers'    => Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters'      => [
                'customerId' => $request->integer('customer_id') ?: null,
                'from'       => $request->query('from'),
                'to'         => $request->query('to'),
            ],
        ]);
    }

    /** One page of payment rows as an HTML fragment plus summary meta. */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalePayment::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.customer-payments._rows', 'payments', [
            'summary' => $this->summaryFor($request),
        ]);
    }

    /** @return Builder<SalePayment> */
    private function filteredBase(Request $request): Builder
    {
        $customerId = $request->integer('customer_id') ?: null;
        $from       = $request->query('from');
        $to         = $request->query('to');

        return SalePayment::query()
            ->settlement()
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('paid_at', '<=', $to));
    }

    /** @return Builder<SalePayment> */
    private function listQuery(Request $request): Builder
    {
        return $this->filteredBase($request)
            ->with(['customer:id,code,name', 'paymentMethod:id,code,name', 'sale:id,number'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id');
    }

    /**
     * @return array{total:string, count:string, customers:string}
     */
    private function summaryFor(Request $request): array
    {
        $agg = $this->filteredBase($request)
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('COUNT(DISTINCT customer_id) as customers')
            ->first();

        return [
            'total'     => format_money($agg->total ?? 0),
            'count'     => number_format((int) ($agg->cnt ?? 0)),
            'customers' => number_format((int) ($agg->customers ?? 0)),
        ];
    }

    /**
     * @param  array{total:string, count:string, customers:string}  $summary
     * @return array<int, array<string, mixed>>
     */
    private function summaryCards(array $summary): array
    {
        return [
            ['label' => __('customer_payments.summary.total'),     'value' => $summary['total'],     'key' => 'total', 'tone' => 'positive'],
            ['label' => __('customer_payments.summary.count'),     'value' => $summary['count'],     'key' => 'count'],
            ['label' => __('customer_payments.summary.customers'), 'value' => $summary['customers'], 'key' => 'customers'],
        ];
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SalePayment::class);

        // `old('customer_id')` wins on a failed re-submit; falls back to
        // the query string (entry point from sale show page passes
        // ?customer_id=… so the picker + open-sales table preload).
        $customerId = (int) (old('customer_id') ?: $request->integer('customer_id')) ?: null;

        $openSales = collect();
        if ($customerId) {
            $openSales = $this->loadOpenSales($customerId);
        }

        return view('admin.customer-payments.create', [
            'customerId' => $customerId,
            // `outstanding_balance` powers the "· Due <amount>" suffix on each
            // option so the cashier can pick who owes without opening each one.
            'customers'  => Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'outstanding_balance']),
            'methods'    => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'requires_reference']),
            'openSales'  => $openSales,
        ]);
    }

    public function store(CustomerPaymentRequest $request, RecordCustomerPayments $record): JsonResponse|RedirectResponse
    {
        $this->authorize('create', SalePayment::class);

        try {
            $record($request->headerData(), $request->allocationData(), $request->user());
        } catch (RuntimeException $e) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => ['_action' => [$e->getMessage()]],
                ], 422);
            }
            return back()->withInput()->with('error', $e->getMessage());
        }

        return $this->jsonOrRedirect(
            $request,
            __('customer_payments.flash.created'),
            route('admin.customer-payments.index'),
        );
    }

    /**
     * JSON list of a customer's open sales — fed to the create form
     * when the picker changes. Same shape the create() view consumes
     * for the initial render.
     */
    public function openSales(Customer $customer): JsonResponse
    {
        $this->authorize('create', SalePayment::class);
        return response()->json($this->loadOpenSales($customer->id));
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function loadOpenSales(int $customerId)
    {
        return Sale::query()
            ->where('customer_id', $customerId)
            ->whereRaw('balance_due > 0')
            ->orderBy('sale_date')
            ->orderBy('id')
            ->get(['id', 'number', 'sale_date', 'currency_code', 'grand_total', 'paid_total', 'balance_due'])
            ->map(fn (Sale $s) => [
                'id'            => $s->id,
                'number'        => $s->number,
                'sale_date'     => $s->sale_date?->toDateString(),
                'currency_code' => $s->currency_code,
                'grand_total'   => $s->grand_total,
                'paid_total'    => $s->paid_total,
                'balance_due'   => $s->balance_due,
            ]);
    }

    public function show(SalePayment $customerPayment): View
    {
        $this->authorize('view', $customerPayment);

        $customerPayment->load(['customer', 'paymentMethod', 'sale', 'createdBy:id,name']);

        return view('admin.customer-payments.show', [
            'payment'   => $customerPayment,
            'siblings'  => $customerPayment->client_uuid
                ? SalePayment::query()
                    ->where('client_uuid', $customerPayment->client_uuid)
                    ->where('id', '!=', $customerPayment->id)
                    ->with('sale:id,number')
                    ->get()
                : collect(),
        ]);
    }
}
