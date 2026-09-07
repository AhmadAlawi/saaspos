<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Roles\CreateRole;
use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\ExportRoles;
use App\Actions\Roles\UpdateRole;
use App\Exceptions\RoleNotDeletable;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Role builder — list + dedicated create/edit pages carrying the
 * permission matrix. Gated by the `roles.manage` permission
 * (docs/features/auth-users.md §6); super admins bypass via Gate::before.
 */
class RoleController extends Controller
{
    use RendersDataTableRows;
    use RespondsJsonOrRedirect;

    /**
     * Roles list — server-paginated. The component search box (`?q=`), the
     * sortable name / permissions / users headers and paging round-trip to
     * {@see rows()} via the generic `rolesIndexPage` (aliased serverTablePage)
     * factory. The `assignments` (user count) rides along as a subquery select.
     */
    public function index(Request $request): View
    {
        $this->authorize('roles.manage');

        $perPage   = $this->dtPerPage($request);
        $paginator = $this->listQuery($request)->paginate($perPage);

        return view('admin.roles.index', [
            'roles'      => $paginator->getCollection(),
            'total'      => $paginator->total(),
            'perPage'    => $perPage,
            'totalPages' => max(1, $paginator->lastPage()),
        ]);
    }

    /** One page of role rows as an HTML fragment. See {@see RendersDataTableRows}. */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('roles.manage');

        $paginator = $this->listQuery($request)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.roles._rows', 'roles');
    }

    /** @return Builder<Role> */
    private function listQuery(Request $request): Builder
    {
        // `assignments` = how many (store,user) rows hold this role — a
        // correlated count, so it's available for both display and sorting
        // without an N+1 per row.
        $query = Role::query()
            ->withCount('permissions')
            ->addSelect(['assignments' => DB::table('store_user')
                ->selectRaw('COUNT(*)')
                ->whereColumn('store_user.role_id', 'roles.id')]);

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"));
        }

        $column = (string) $request->query('sort', '');
        $dir    = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($column === 'users') {
            // Sort by the correlated count directly (the alias isn't portable in
            // every DB's ORDER BY). `$dir` is whitelisted above.
            return $query->orderByRaw('(SELECT COUNT(*) FROM store_user WHERE store_user.role_id = roles.id) '.$dir);
        }

        $map = ['name' => 'name', 'permissions' => 'permissions_count'];
        if (isset($map[$column])) {
            return $query->orderBy($map[$column], $dir)->orderBy('id', 'desc');
        }

        return $query->ordered();
    }

    public function create(): View
    {
        $this->authorize('roles.manage');

        return view('admin.roles.create', $this->formContext());
    }

    public function store(RoleRequest $request, CreateRole $create): JsonResponse|RedirectResponse
    {
        $this->authorize('roles.manage');

        $role = ($create)($request->roleAttributes(), $request->permissionIds());

        return $this->jsonOrRedirect(
            $request,
            __('roles.flash.created', ['name' => $role->name]),
            route('admin.roles.index'),
        );
    }

    public function edit(Role $role): View
    {
        $this->authorize('roles.manage');

        return view('admin.roles.edit', array_merge($this->formContext(), [
            'role'        => $role,
            'selectedIds' => $role->permissions()->pluck('permissions.id')->all(),
        ]));
    }

    public function update(RoleRequest $request, Role $role, UpdateRole $update): JsonResponse|RedirectResponse
    {
        $this->authorize('roles.manage');

        ($update)($role, $request->roleAttributes(), $request->permissionIds());

        return $this->jsonOrRedirect(
            $request,
            __('roles.flash.updated', ['name' => $role->name]),
            route('admin.roles.index'),
        );
    }

    public function destroy(Request $request, Role $role, DeleteRole $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('roles.manage');

        if (pos_is_demo()) {
            return $this->demoBlocked($request, route('admin.roles.index'));
        }

        $name = $role->name;

        try {
            ($delete)($role);
        } catch (RoleNotDeletable $e) {
            return $this->jsonOrError(
                $request,
                __('roles.errors.'.$e->reason, ['name' => $name, 'count' => $e->count]),
                route('admin.roles.index'),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('roles.flash.deleted', ['name' => $name]),
            route('admin.roles.index'),
        );
    }

    public function export(Request $request, ExportRoles $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', Role::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    /* ── Helpers ────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function formContext(): array
    {
        return [
            // Permissions grouped for the collapsible matrix.
            'groups' => Permission::query()->ordered()->get()->groupBy('group'),
        ];
    }
}
