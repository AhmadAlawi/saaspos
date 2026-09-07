<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Stores\CreateStore;
use App\Actions\Stores\DeactivateStore;
use App\Actions\Stores\DeleteStore;
use App\Actions\Stores\SetDefaultStore;
use App\Actions\Stores\UpdateStore;
use App\Exceptions\StoreNotDeactivatable;
use App\Exceptions\StoreNotDeletable;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRequest;
use App\Models\Store;
use App\Support\Countries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Stores module — list + dedicated create/edit pages. Stores carry too
 * many config fields for the side-editor pattern used by Brands/Units,
 * so create + edit each get their own page (same call as Products).
 *
 * Store-level settings tabs (terminals, users, per-store overrides) and
 * the detail/overview screen land with the wider multi-store rollout.
 */
class StoreController extends Controller
{
    use RespondsJsonOrRedirect;

    public function index(): View
    {
        $this->authorize('viewAny', Store::class);

        $stores = Store::query()
            ->withCount('users')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('admin.stores.index', [
            'stores'   => $stores,
            'activeId' => current_store_id(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Store::class);

        return view('admin.stores.create', [
            'countries'        => Countries::all(),
            'receiptTemplates' => \App\Models\ReceiptTemplate::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreRequest $request, CreateStore $create, SetDefaultStore $setDefault): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Store::class);

        // Localization is system-wide (not per-store), so new stores inherit
        // the system currency + a default time zone rather than asking for it.
        $data = array_merge($request->persistedAttributes(), $this->localizationDefaults());

        $store = ($create)($data, $request->user());

        // Explicit "make this the default" from the form. CreateStore
        // already auto-defaults the very first store.
        if ($request->boolean('is_default') && ! $store->is_default) {
            ($setDefault)($store);
        }

        return $this->jsonOrRedirect(
            $request,
            __('stores.flash.created', ['name' => $store->name]),
            route('admin.stores.index'),
        );
    }

    public function edit(Store $store): View
    {
        $this->authorize('update', $store);

        return view('admin.stores.edit', [
            'store'            => $store,
            'countries'        => Countries::all(),
            'receiptTemplates' => \App\Models\ReceiptTemplate::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(StoreRequest $request, Store $store, UpdateStore $update, SetDefaultStore $setDefault): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $store);

        ($update)($store, $request->persistedAttributes());

        // Promote to default if requested. You can't un-default by
        // unchecking — set another store as default instead.
        if ($request->boolean('is_default') && ! $store->fresh()->is_default) {
            ($setDefault)($store);
        }

        return $this->jsonOrRedirect(
            $request,
            __('stores.flash.updated', ['name' => $store->name]),
            route('admin.stores.index'),
        );
    }

    public function setDefault(Store $store, SetDefaultStore $setDefault): RedirectResponse
    {
        $this->authorize('update', $store);

        ($setDefault)($store);

        return redirect()
            ->route('admin.stores.index')
            ->with('success', __('stores.flash.default_set', ['name' => $store->name]));
    }

    public function toggle(Request $request, Store $store, DeactivateStore $deactivate): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $store);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        try {
            ($deactivate)($store, (bool) $data['is_active']);
        } catch (StoreNotDeactivatable $e) {
            $message = __('stores.errors.'.$e->reason, ['name' => $store->name]);

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return redirect()->route('admin.stores.index')->with('error', $message);
        }

        $key = $data['is_active'] ? 'stores.flash.activated' : 'stores.flash.deactivated';

        if ($request->expectsJson()) {
            return response()->json([
                'ok'        => true,
                'id'        => $store->id,
                'is_active' => (bool) $store->is_active,
                'message'   => __($key, ['name' => $store->name]),
            ]);
        }

        return redirect()
            ->route('admin.stores.index')
            ->with('success', __($key, ['name' => $store->name]));
    }

    public function destroy(Request $request, Store $store, DeleteStore $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $store);

        if (pos_is_demo()) {
            return $this->demoBlocked($request, route('admin.stores.index'));
        }

        $name = $store->name;

        try {
            ($delete)($store);
        } catch (StoreNotDeletable $e) {
            return $this->jsonOrError(
                $request,
                __('stores.errors.'.$e->reason, ['name' => $name]),
                route('admin.stores.index'),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('stores.flash.deleted', ['name' => $name]),
            route('admin.stores.index'),
        );
    }

    public function export(Request $request, \App\Actions\Stores\ExportStores $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', Store::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format);
    }

    /* ── Helpers ────────────────────────────────────────────────── */

    /**
     * System-wide localization defaults applied to new stores. Currency,
     * time zone, locale, and rounding are configured once for the whole
     * install — not per store — so we inherit them here instead of asking
     * on the form. Time zone comes from Settings → Regional
     * (`app_timezone()`).
     *
     * @return array<string, mixed>
     */
    private function localizationDefaults(): array
    {
        return [
            'currency_code' => app_currency()['code'],
            'timezone'      => app_timezone(),
            'locale'        => null,
            'rounding_mode' => 'half_up',
        ];
    }
}
