<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Expenses\CreateExpense;
use App\Actions\Expenses\DeleteExpense;
use App\Actions\Expenses\ExportExpenses;
use App\Actions\Expenses\UpdateExpense;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Expenses admin — CRUD for the drawer's cash-out side
 * (docs/features/cash-drawer-shifts.md §4.3). Scoped to the session
 * store (topbar switcher), like Sales / Purchases. Approval workflow,
 * recurring schedules, receipt-image upload, and journal posting are
 * deferred to later slices.
 */
class ExpenseController extends Controller
{
    use RendersDataTableRows;
    use RespondsJsonOrRedirect;

    /**
     * Expenses — server-paginated. Filters (category / date range / search) +
     * paging round-trip to {@see rows()} via the generic `expensesPage`
     * (aliased salesIndexPage) factory. Store-scoped to the active store.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery($request)->paginate($perPage);
        $summary   = $this->summaryFor($request);

        return view('admin.expenses.index', [
            'expenses'     => $paginator->getCollection(),
            'total'        => $paginator->total(),
            'perPage'      => $perPage,
            'totalPages'   => max(1, $paginator->lastPage()),
            'summaryCards' => $this->summaryCards($summary),
            'categories'   => ExpenseCategory::query()->active()->ordered()->get(['id', 'name']),
            'filters'      => [
                'q'          => trim((string) $request->query('q', '')),
                'categoryId' => $request->integer('category_id') ?: null,
                'from'       => $request->query('from'),
                'to'         => $request->query('to'),
            ],
        ]);
    }

    /** One page of expense rows as an HTML fragment plus summary meta. */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.expenses._rows', 'expenses', [
            'summary' => $this->summaryFor($request),
        ]);
    }

    /** @return Builder<Expense> */
    private function filteredBase(Request $request): Builder
    {
        $storeId    = current_store_id() ?: default_store_id();
        $q          = trim((string) $request->query('q', ''));
        $categoryId = $request->integer('category_id') ?: null;
        $from       = $request->query('from');
        $to         = $request->query('to');

        return Expense::query()
            ->when($storeId, fn ($qb) => $qb->where('store_id', $storeId))
            ->when($categoryId, fn ($qb) => $qb->where('category_id', $categoryId))
            ->when($from, fn ($qb) => $qb->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($qb) => $qb->whereDate('expense_date', '<=', $to))
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('number', 'like', "%{$q}%")
                      ->orWhere('reference', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
                });
            });
    }

    /** @return Builder<Expense> */
    private function listQuery(Request $request): Builder
    {
        return $this->filteredBase($request)
            ->with(['category:id,name', 'paymentMethod:id,name', 'supplier:id,name'])
            ->ordered();
    }

    /**
     * @return array{count:string, total:string}
     */
    private function summaryFor(Request $request): array
    {
        $agg = $this->filteredBase($request)
            ->selectRaw('COUNT(*) as cnt')
            // `total` is the amount + tax_amount accessor — mirror it in SQL.
            ->selectRaw('COALESCE(SUM(amount + tax_amount), 0) as total')
            ->first();

        return [
            'count' => number_format((int) ($agg->cnt ?? 0)),
            'total' => format_money($agg->total ?? 0),
        ];
    }

    /**
     * @param  array{count:string, total:string}  $summary
     * @return array<int, array<string, mixed>>
     */
    private function summaryCards(array $summary): array
    {
        return [
            ['label' => __('expenses.summary.count'), 'value' => $summary['count'], 'key' => 'count'],
            ['label' => __('expenses.summary.total'), 'value' => $summary['total'], 'key' => 'total', 'tone' => 'warning'],
        ];
    }

    public function create(): View
    {
        $this->authorize('create', Expense::class);

        $expense = new Expense(['expense_date' => now()->toDateString()]);

        return view('admin.expenses.edit', $this->editorPayload($expense));
    }

    public function store(ExpenseRequest $request, CreateExpense $create): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Expense::class);

        $storeId = current_store_id() ?: default_store_id();
        $expense = $create($request->expenseData(), $request->user(), (int) $storeId);

        return $this->jsonOrRedirect(
            $request,
            __('expenses.flash.created', ['number' => $expense->number]),
            route('admin.expenses.index'),
        );
    }

    public function edit(Expense $expense): View
    {
        $this->authorize('update', $expense);

        return view('admin.expenses.edit', $this->editorPayload($expense));
    }

    public function update(ExpenseRequest $request, Expense $expense, UpdateExpense $update): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $expense);

        $update($expense, $request->expenseData(), $request->user());

        return $this->jsonOrRedirect(
            $request,
            __('expenses.flash.updated', ['number' => $expense->number]),
            route('admin.expenses.index'),
        );
    }

    public function destroy(Request $request, Expense $expense, DeleteExpense $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $expense);

        $number = $expense->number;
        $delete($expense);

        return $this->jsonOrRedirect(
            $request,
            __('expenses.flash.deleted', ['number' => $number]),
            route('admin.expenses.index'),
        );
    }

    public function export(Request $request, ExportExpenses $export): StreamedResponse
    {
        $this->authorize('export', Expense::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format, current_store_id() ?: default_store_id());
    }

    /** @return array<string, mixed> */
    private function editorPayload(Expense $expense): array
    {
        return [
            'expense'        => $expense,
            'categories'     => ExpenseCategory::query()->active()->ordered()->get(['id', 'name']),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'suppliers'      => Supplier::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name']),
        ];
    }
}
