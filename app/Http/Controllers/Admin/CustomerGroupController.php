<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Customers\ExportCustomerGroups;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerGroupRequest;
use App\Models\CustomerGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customer groups admin — master-detail picklist (list on the left,
 * sticky editor on the right). AJAX-driven like Categories / Drug
 * Schedules / Adjustment Reasons: saves and deletes flow through XHR,
 * the server returns the freshly-rendered list HTML, and the client
 * swaps it in without a page reload.
 *
 * DESC default sort (newest first) — system-wide convention for tables
 * without a manual `sort_order` column.
 */
class CustomerGroupController extends Controller
{
    use RendersDataTableRows;

    /**
     * Customer groups — master-detail. The DOM list is server-paginated; every
     * save/delete reloads the current page. The editor's rowsById covers the
     * full (bounded) set so it can open any group off the current page.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CustomerGroup::class);

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery(new Request(['per_page' => $perPage]))->paginate($perPage);
        $allRows   = CustomerGroup::query()->orderByDesc('id')->get();

        $selectedId = $request->integer('selected');
        $isNew      = $request->boolean('new');
        $selected   = $selectedId ? $allRows->firstWhere('id', $selectedId) : null;

        return view('admin.customer-groups.index', [
            'rows'       => $paginator->getCollection(),
            'allRows'    => $allRows,
            'total'      => $paginator->total(),
            'perPage'    => $perPage,
            'totalPages' => max(1, $paginator->lastPage()),
            'selected'   => $selected,
            'isNew'      => $isNew,
        ]);
    }

    /** One page of group rows as an HTML fragment. See {@see RendersDataTableRows}. */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerGroup::class);

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.customer-groups._list', 'rows');
    }

    /** @return Builder<CustomerGroup> */
    private function listQuery(Request $request): Builder
    {
        $query = CustomerGroup::query();

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        $column = (string) $request->query('sort', '');
        $dir    = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $map    = ['name' => 'name', 'id' => 'id'];
        if (isset($map[$column])) {
            return $query->orderBy($map[$column], $dir)->orderBy('id', 'desc');
        }

        return $query->orderByDesc('id');
    }

    public function store(CustomerGroupRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', CustomerGroup::class);

        $row     = CustomerGroup::create($request->persistedAttributes());
        $message = __('customers.groups.flash.created', ['name' => $row->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($row->id, $message);
        }

        return redirect()
            ->route('admin.customer-groups.index', ['selected' => $row->id])
            ->with('success', $message);
    }

    public function update(CustomerGroupRequest $request, CustomerGroup $customerGroup): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $customerGroup);

        $customerGroup->update($request->persistedAttributes());
        $message = __('customers.groups.flash.updated', ['name' => $customerGroup->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($customerGroup->id, $message);
        }

        return redirect()
            ->route('admin.customer-groups.index', ['selected' => $customerGroup->id])
            ->with('success', $message);
    }

    public function destroy(Request $request, CustomerGroup $customerGroup): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $customerGroup);

        $name = $customerGroup->name;

        if ($customerGroup->customers()->exists()) {
            $msg = __('customers.groups.errors.in_use', ['name' => $name]);
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()
                ->route('admin.customer-groups.index', ['selected' => $customerGroup->id])
                ->with('error', $msg);
        }

        $customerGroup->delete();
        $message = __('customers.groups.flash.deleted', ['name' => $name]);

        if ($request->wantsJson()) {
            return $this->freshListJson(null, $message);
        }

        return redirect()
            ->route('admin.customer-groups.index')
            ->with('success', $message);
    }

    /**
     * Server-rendered list HTML + a structured row snapshot for the
     * Alpine `rowsById` lookup. Returned after every successful save /
     * delete / toggle so the client can swap in one go without a reload.
     */

    public function export(Request $request, ExportCustomerGroups $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', CustomerGroup::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    private function freshListJson(?int $selectedId, string $message): JsonResponse
    {
        $rows = CustomerGroup::query()->orderByDesc('id')->get();

        $listHtml = view('admin.customer-groups._list', ['rows' => $rows])->render();

        $rowsForJs = $rows->map(fn (CustomerGroup $r) => [
            'id'                       => $r->id,
            'name'                     => $r->name,
            'default_discount_percent' => $r->default_discount_percent !== null ? (float) $r->default_discount_percent : null,
            'is_active'                => (bool) $r->is_active,
            'updated_at'               => $r->updated_at?->diffForHumans(),
        ])->values();

        return response()->json([
            'ok'        => true,
            'message'   => $message,
            'id'        => $selectedId,
            'list_html' => $listHtml,
            'rows'      => $rowsForJs,
        ]);
    }
}
