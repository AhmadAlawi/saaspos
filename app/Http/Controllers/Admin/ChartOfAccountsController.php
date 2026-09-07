<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Accounting\CreateAccount;
use App\Actions\Accounting\CreateAccountGroup;
use App\Actions\Accounting\DeleteAccount;
use App\Actions\Accounting\DeleteAccountGroup;
use App\Actions\Accounting\UpdateAccount;
use App\Actions\Accounting\UpdateAccountGroup;
use App\Exceptions\AccountGroupProtected;
use App\Exceptions\AccountProtected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountGroupRequest;
use App\Http\Requests\Admin\AccountRequest;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Services\Accounting\AccountUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Chart of Accounts (docs/features/accounting.md §3.3). A read-friendly tree
 * grouped by the account-group hierarchy with a sticky right-card editor;
 * create / edit / delete happen over AJAX and the server returns the freshly
 * rendered tree so the client swaps it in (same shape as the tax-components
 * master-detail). Viewing needs `accounting.view`; writing needs
 * `accounting.chart.update`.
 */
class ChartOfAccountsController extends Controller
{
    public function __construct(private AccountUsage $usage) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('accounting.view'), 403);

        return view('admin.accounting.chart.index', [
            'roots'        => $this->treeRoots(),
            'meta'         => $this->accountMeta(),
            'rows'         => $this->rowsForJs(),
            'groupOptions' => $this->groupOptions(),
            'groups'       => $this->groupsForJs(),
            'selectedId'   => $request->integer('selected') ?: null,
        ]);
    }

    public function store(AccountRequest $request, CreateAccount $create): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('accounting.chart.update'), 403);

        $account = $create($request->persistedAttributes());
        $message = __('accounting.chart.flash.created', ['name' => $account->name]);

        if ($request->wantsJson()) {
            return $this->freshTreeJson($account->id, $message);
        }

        return redirect()
            ->route('admin.accounting.chart-of-accounts.index', ['selected' => $account->id])
            ->with('success', $message);
    }

    public function update(AccountRequest $request, Account $account, UpdateAccount $update): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('accounting.chart.update'), 403);

        try {
            $update($account, $request->persistedAttributes());
        } catch (AccountProtected $e) {
            return $this->guardFailed($request, $account->id, $e->getMessage());
        }

        $message = __('accounting.chart.flash.updated', ['name' => $account->name]);

        if ($request->wantsJson()) {
            return $this->freshTreeJson($account->id, $message);
        }

        return redirect()
            ->route('admin.accounting.chart-of-accounts.index', ['selected' => $account->id])
            ->with('success', $message);
    }

    public function destroy(Request $request, Account $account, DeleteAccount $delete): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('accounting.chart.update'), 403);

        $name = $account->name;

        try {
            $delete($account);
        } catch (AccountProtected $e) {
            return $this->guardFailed($request, $account->id, $e->getMessage());
        }

        $message = __('accounting.chart.flash.deleted', ['name' => $name]);

        if ($request->wantsJson()) {
            return $this->freshTreeJson(null, $message);
        }

        return redirect()
            ->route('admin.accounting.chart-of-accounts.index')
            ->with('success', $message);
    }

    // ── Account groups ─────────────────────────────────────────────────

    public function storeGroup(AccountGroupRequest $request, CreateAccountGroup $create): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('accounting.chart.update'), 403);

        $group = $create($request->validated());

        return $this->groupSaved($request, __('accounting.chart.groups.flash.created', ['name' => $group->name]));
    }

    public function updateGroup(AccountGroupRequest $request, AccountGroup $group, UpdateAccountGroup $update): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('accounting.chart.update'), 403);

        try {
            $update($group, $request->validated());
        } catch (AccountGroupProtected $e) {
            return $this->groupFailed($request, $e->getMessage());
        }

        return $this->groupSaved($request, __('accounting.chart.groups.flash.updated', ['name' => $group->name]));
    }

    public function destroyGroup(Request $request, AccountGroup $group, DeleteAccountGroup $delete): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('accounting.chart.update'), 403);

        $name = $group->name;

        try {
            $delete($group);
        } catch (AccountGroupProtected $e) {
            return $this->groupFailed($request, $e->getMessage());
        }

        return $this->groupSaved($request, __('accounting.chart.groups.flash.deleted', ['name' => $name]));
    }

    // ── Internals ──────────────────────────────────────────────────────

    /** Group op succeeded — the client reloads to reflow the whole tree. */
    private function groupSaved(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()
            ->route('admin.accounting.chart-of-accounts.index')
            ->with('success', $message);
    }

    private function groupFailed(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        return redirect()
            ->route('admin.accounting.chart-of-accounts.index')
            ->with('error', $message);
    }

    private function guardFailed(Request $request, int $id, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        return redirect()
            ->route('admin.accounting.chart-of-accounts.index', ['selected' => $id])
            ->with('error', $message);
    }

    /** Root groups with their whole subtree + accounts eager-loaded. */
    private function treeRoots()
    {
        return AccountGroup::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['accountsOrdered', 'childrenRecursive'])
            ->get();
    }

    /** @return array<int, array{balance:string, locked:bool, mapped:bool, can_delete:bool}> */
    private function accountMeta(): array
    {
        $balances = $this->usage->balances();
        $posted   = $this->usage->postedAccountIds();
        $mapped   = $this->usage->mappedAccountIds();

        return Account::query()->get(['id', 'is_system'])->mapWithKeys(fn (Account $a) => [
            $a->id => [
                'balance'    => $balances[$a->id] ?? '0',
                'locked'     => isset($posted[$a->id]),
                'mapped'     => isset($mapped[$a->id]),
                'can_delete' => ! $a->is_system && ! isset($posted[$a->id]) && ! isset($mapped[$a->id]),
            ],
        ])->all();
    }

    /** Flat account list for the editor (Alpine `rowsById`). */
    private function rowsForJs(): \Illuminate\Support\Collection
    {
        $meta = $this->accountMeta();

        return Account::query()->orderBy('code')->get()->map(fn (Account $a) => [
            'id'               => $a->id,
            'code'             => $a->code,
            'name'             => $a->name,
            'account_group_id' => $a->account_group_id,
            'is_active'        => (bool) $a->is_active,
            'is_system'        => (bool) $a->is_system,
            'code_locked'      => $meta[$a->id]['locked'] ?? false,
            'can_delete'       => $meta[$a->id]['can_delete'] ?? false,
        ])->values();
    }

    /** Every group as a flat "Assets › Current Assets" option, sorted by path. */
    private function groupOptions(): array
    {
        $groups = AccountGroup::query()->orderBy('sort_order')->get()->keyBy('id');

        $path = function (AccountGroup $g) use ($groups, &$path): string {
            return $g->parent_id && $groups->has($g->parent_id)
                ? $path($groups->get($g->parent_id)).' › '.$g->name
                : $g->name;
        };

        return $groups->map(fn (AccountGroup $g) => ['id' => $g->id, 'label' => $path($g)])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /** Full group list for the JS group editor (parent picker + guard flags). */
    private function groupsForJs(): \Illuminate\Support\Collection
    {
        $groups = AccountGroup::query()->orderBy('sort_order')->get();
        $byId   = $groups->keyBy('id');

        $path = function (AccountGroup $g) use ($byId, &$path): string {
            return $g->parent_id && $byId->has($g->parent_id)
                ? $path($byId->get($g->parent_id)).' › '.$g->name
                : $g->name;
        };

        $parentsWithChildren = AccountGroup::query()->whereNotNull('parent_id')
            ->distinct()->pluck('parent_id')->map(fn ($x) => (int) $x)->flip();
        $groupsWithAccounts = Account::query()
            ->distinct()->pluck('account_group_id')->map(fn ($x) => (int) $x)->flip();

        return $groups->map(fn (AccountGroup $g) => [
            'id'         => $g->id,
            'name'       => $g->name,
            'parent_id'  => $g->parent_id ? (int) $g->parent_id : null,
            'type'       => $g->type,
            'path_label' => $path($g),
            'is_system'  => (bool) $g->is_system,
            'can_delete' => ! $g->is_system
                && ! $parentsWithChildren->has($g->id)
                && ! $groupsWithAccounts->has($g->id),
        ])->values();
    }

    private function freshTreeJson(?int $selectedId, string $message): JsonResponse
    {
        $treeHtml = view('admin.accounting.chart._tree', [
            'roots' => $this->treeRoots(),
            'meta'  => $this->accountMeta(),
        ])->render();

        return response()->json([
            'ok'        => true,
            'message'   => $message,
            'id'        => $selectedId,
            'tree_html' => $treeHtml,
            'rows'      => $this->rowsForJs(),
        ]);
    }
}
