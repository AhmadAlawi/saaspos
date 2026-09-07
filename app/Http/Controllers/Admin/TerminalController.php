<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Terminals\CreateTerminal;
use App\Actions\Terminals\DeleteTerminal;
use App\Actions\Terminals\ExportTerminals;
use App\Actions\Terminals\UpdateTerminal;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TerminalRequest;
use App\Models\Terminal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master-detail CRUD for checkout terminals (Settings → Terminals).
 * Same shape as {@see BrandController}: flat list on the left, sticky
 * editor on the right; saves/deletes run over AJAX and the server
 * returns the freshly-rendered list HTML for a one-shot client swap.
 *
 * The editor also configures the terminal's hardware (receipt-printer
 * mode + WebUSB pairing, paper, cut, cash drawer), folded into the
 * `default_printer_config` JSON by {@see TerminalRequest}.
 */
class TerminalController extends Controller
{
    use RendersDataTableRows;

    /** Sortable columns for the list's sort menu → SQL column. */
    private const SORT_COLUMNS = [
        'name' => 'name',
        'code' => 'code',
        'id'   => 'id',
    ];

    /**
     * Terminals list — server-paginated. Only the first page renders inline;
     * search, sort and paging round-trip to {@see rows()}. Storewise-scoped:
     * always the active store, never a company-wide list.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Terminal::class);

        // $store and the list derive from the same id so the header badge, the
        // list, and the editor can never disagree.
        $store   = current_store();
        $perPage = $this->dtPerPage($request);

        $paginator = $this->listQuery(new Request(['per_page' => $perPage]), $store?->id)->paginate($perPage);
        $terminals = $paginator->getCollection();

        return view('admin.terminals.index', [
            'terminals'        => $terminals,
            'store'            => $store,
            // Which terminal THIS browser is currently bound to, if any.
            'activeTerminalId' => current_terminal_id(),
            'perPage'          => $perPage,
            'total'            => $paginator->total(),
            'totalPages'       => max(1, $paginator->lastPage()),
            // Rich per-row data the list factory needs client-side (toggle
            // resend, select/kiosk guards) — seeded for the first page.
            'rowsPayload'      => $this->rowsPayload($terminals)->all(),
        ]);
    }

    /**
     * One page of terminal rows as an HTML fragment, plus the rich `rows`
     * payload the list factory rebuilds `rowsById` from. See {@see RendersDataTableRows}.
     */
    public function rows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Terminal::class);

        $storeId   = current_store_id() ?: default_store_id();
        $paginator = $this->listQuery($request, $storeId)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.terminals._list', 'terminals', [
            'rows' => $this->rowsPayload($paginator->getCollection())->all(),
        ]);
    }

    /**
     * Store-scoped list query with optional search + sort. A null store id
     * (no store exists yet) yields an empty set rather than every store's
     * terminals — the page is storewise, never company-wide.
     *
     * @return Builder<Terminal>
     */
    private function listQuery(Request $request, ?int $storeId): Builder
    {
        $query = Terminal::query()
            ->with('store:id,name')
            ->when($storeId, fn ($q) => $q->forStore($storeId), fn ($q) => $q->whereRaw('1 = 0'));

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        $this->applySort($query, $request);

        return $query;
    }

    /** @param Builder<Terminal> $query */
    private function applySort(Builder $query, Request $request): void
    {
        $column = (string) $request->query('sort', '');
        $dir    = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (isset(self::SORT_COLUMNS[$column])) {
            $query->orderBy(self::SORT_COLUMNS[$column], $dir);
            $query->orderBy('id', 'desc');

            return;
        }

        // Default: the manual sort_order (ordered scope).
        $query->ordered();
    }

    /**
     * Rich per-row data the list factory needs client-side. Shared by the
     * inline first page, the rows() endpoint, and freshListJson().
     *
     * @param  Collection<int, Terminal>  $terminals
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function rowsPayload(Collection $terminals): \Illuminate\Support\Collection
    {
        return $terminals->map(fn (Terminal $t) => [
            'id'         => $t->id,
            'store_id'   => $t->store_id,
            'store_name' => $t->store?->name,
            'code'       => $t->code,
            'name'       => $t->name,
            'is_active'  => (bool) $t->is_active,
            'config'     => $t->default_printer_config['receipt_printer'] ?? null,
            'cfd'        => $t->cfdConfigForEditor(),
            'type'       => $t->type,
            'kiosk'      => $t->kioskConfigForEditor(),
            'updated_at' => $t->updated_at?->diffForHumans(),
        ])->values();
    }

    /** The "add a terminal" page. */
    public function create(): View
    {
        $this->authorize('create', Terminal::class);

        return $this->form(null);
    }

    /** The "edit terminal" page. */
    public function edit(Terminal $terminal): View
    {
        $this->authorize('update', $terminal);

        return $this->form($terminal);
    }

    /**
     * Render the shared add/edit form. `initial` is hydrated from the
     * terminal (edit) or blanks (create) — or from `old()` when we've
     * bounced back from a failed validation, so the operator's input and
     * the enable/kiosk toggles survive the round-trip.
     */
    private function form(?Terminal $terminal): View
    {
        $store = current_store();

        if (old('name') !== null || old('station_type') !== null) {
            // Validation bounce — rebuild the editor state from old input.
            $initial = [
                'id'        => $terminal?->id,
                'code'      => (string) old('code', ''),
                'name'      => (string) old('name', ''),
                'is_active' => (bool) old('is_active', false),
                'receipt_template_id' => old('receipt_template_id') ? (int) old('receipt_template_id') : null,
                'config'    => [
                    'mode'                => (string) old('printer_mode', 'browser_print'),
                    'paper_width'         => (string) old('paper_width', '80mm'),
                    'cut_paper'           => (bool) old('cut_paper', true),
                    'open_drawer_on_cash' => (bool) old('open_drawer_on_cash', true),
                    'drawer_pin'          => (int) old('drawer_pin', 2),
                    'webusb_device_descriptor' => [
                        'vendor_id'  => (string) old('webusb_vendor_id', ''),
                        'product_id' => (string) old('webusb_product_id', ''),
                    ],
                ],
                'cfd' => [
                    'enabled'         => (bool) old('cfd_enabled', false),
                    'transport'       => (string) old('cfd_transport', 'same_machine'),
                    'welcome_text'    => (string) old('cfd_welcome', ''),
                    'thankyou_text'   => (string) old('cfd_thankyou', ''),
                    'attract_media'   => $this->oldMedia('cfd_media'),
                    'attract_seconds' => (int) old('attract_seconds', 8),
                ],
                'type'  => (string) old('station_type', 'register'),
                'kiosk' => [
                    'enabled'                 => (bool) old('kiosk_enabled', false),
                    'mode'                    => (string) old('kiosk_mode', 'checkout'),
                    'welcome_text'            => (string) old('kiosk_welcome', ''),
                    'thankyou_text'           => (string) old('kiosk_thankyou', ''),
                    'idle_timeout_seconds'    => (int) old('kiosk_idle_timeout', 60),
                    'customer'                => (string) old('kiosk_customer', 'off'),
                    'category_ids'            => collect(old('kiosk_categories', []))->map(fn ($id) => (int) $id)->all(),
                    'payment_method_ids'      => collect(old('kiosk_payment_methods', []))->map(fn ($id) => (int) $id)->all(),
                    'upi_method_id'           => old('kiosk_upi_method') ? (int) old('kiosk_upi_method') : null,
                    'sound_enabled'           => (bool) old('kiosk_sound', true),
                    'thankyou_seconds'        => (int) old('kiosk_thankyou_seconds', 25),
                    'pickup_prefix'           => (string) old('kiosk_pickup_prefix', 'K'),
                    'allow_order_note'        => (bool) old('kiosk_allow_note', true),
                    'supervisor_pin_required' => (bool) old('kiosk_pin_required', false),
                    'attract_media'           => $this->oldMedia('kiosk_media'),
                    'attract_seconds'         => (int) old('kiosk_attract_seconds', 8),
                ],
            ];
        } else {
            $initial = [
                'id'        => $terminal?->id,
                'code'      => $terminal?->code ?? '',
                'name'      => $terminal?->name ?? '',
                'is_active' => $terminal ? (bool) $terminal->is_active : true,
                'receipt_template_id' => $terminal?->receipt_template_id,
                'config'    => $terminal?->default_printer_config['receipt_printer'] ?? null,
                'cfd'       => $terminal ? $terminal->cfdConfigForEditor() : null,
                'type'      => $terminal?->type ?? 'register',
                'kiosk'     => $terminal ? $terminal->kioskConfigForEditor() : null,
            ];
        }

        return view('admin.terminals.form', [
            'store'           => $store,
            'terminal'        => $terminal,
            'initial'         => $initial,
            'receiptTemplates' => \App\Models\ReceiptTemplate::where('is_active', true)->orderBy('name')->get(),
            'kioskCategories' => \App\Models\Category::query()
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // Gateways the merchant can hand the kiosk's "Pay now" button.
            // Only online gateways qualify — a self-serve kiosk can't take
            // cash, and the customer pays on their own phone after scanning.
            'kioskPaymentMethods' => \App\Models\PaymentMethod::query()
                ->whereIn('provider', \App\Models\Sale::GATEWAY_PROVIDERS)
                ->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'provider']),
            // Non-gateway methods carrying a UPI `vpa` — the only ones that can
            // render a `upi://pay?…` QR the shopper scans at the machine.
            'kioskUpiMethods' => \App\Models\PaymentMethod::query()
                ->active()
                ->withoutGateway()
                ->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'provider', 'provider_credentials'])
                ->filter(fn (\App\Models\PaymentMethod $m) => $m->upiVpa() !== null)
                ->values(),
        ]);
    }

    /**
     * Rebuild the `{path, url}` media list from an `old()` array of paths
     * after a validation bounce, so the preview thumbnails survive.
     *
     * @return array<int, array{path: string, url: string}>
     */
    private function oldMedia(string $key): array
    {
        return collect(old($key, []))
            ->filter(fn ($p) => is_string($p) && $p !== '')
            ->map(fn ($p) => ['path' => $p, 'url' => \Illuminate\Support\Facades\Storage::disk('public')->url($p)])
            ->values()->all();
    }

    /**
     * Bind the current browser/workstation to a terminal by setting the
     * long-lived `pos_terminal_id` cookie that {@see current_terminal()}
     * reads. Because a terminal is scoped to one store, this also makes
     * that store the active store so the binding resolves immediately
     * (mirrors {@see StoreSwitchController}, including its access guard).
     */
    public function select(Request $request, Terminal $terminal): JsonResponse
    {
        $this->authorize('viewAny', Terminal::class);

        if (! $terminal->is_active) {
            return response()->json([
                'ok'      => false,
                'message' => __('terminals.select.inactive'),
            ], 422);
        }

        $user = $request->user();
        abort_unless($user && $user->canAccessStore($terminal->store_id), 403);

        $from = current_store_id();
        if ($from !== $terminal->store_id) {
            $request->session()->put('active_store_id', $terminal->store_id);
            do_action('store.switched', $from, $terminal->store_id, $user);
        }

        cookie()->queue(cookie()->forever('pos_terminal_id', (string) $terminal->id));

        return response()->json([
            'ok'          => true,
            'message'     => __('terminals.select.flash', ['name' => $terminal->name]),
            'selected_id' => $terminal->id,
        ]);
    }

    /** Unbind this browser from any terminal (clears the cookie). */
    public function clearSelection(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Terminal::class);

        cookie()->queue(cookie()->forget('pos_terminal_id'));

        return response()->json([
            'ok'          => true,
            'message'     => __('terminals.select.cleared'),
            'selected_id' => null,
        ]);
    }

    public function store(TerminalRequest $request, CreateTerminal $create): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Terminal::class);

        $terminal = ($create)($request->persistedAttributes());
        $message  = __('terminals.flash.created', ['name' => $terminal->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($terminal->id, $message);
        }

        return redirect()
            ->route('admin.terminals.index', ['selected' => $terminal->id])
            ->with('success', $message);
    }

    public function update(TerminalRequest $request, Terminal $terminal, UpdateTerminal $update): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $terminal);

        ($update)($terminal, $request->persistedAttributes());
        $message = __('terminals.flash.updated', ['name' => $terminal->name]);

        if ($request->wantsJson()) {
            return $this->freshListJson($terminal->id, $message);
        }

        return redirect()
            ->route('admin.terminals.index', ['selected' => $terminal->id])
            ->with('success', $message);
    }

    public function destroy(Request $request, Terminal $terminal, DeleteTerminal $delete): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $terminal);

        $name = $terminal->name;
        ($delete)($terminal);
        $message = __('terminals.flash.deleted', ['name' => $name]);

        if ($request->wantsJson()) {
            return $this->freshListJson(null, $message);
        }

        return redirect()
            ->route('admin.terminals.index')
            ->with('success', $message);
    }

    /**
     * Store one customer-display attract-media image and return its path +
     * URL. The editor uploads on pick (so the merchant sees a preview
     * instantly) and submits the stored PATH with the terminal form; the
     * CFD resolves the path back to a URL at render. Kept small on purpose —
     * a JPEG/PNG/WebP under 2 MB, mirroring the brand-logo upload rules.
     */
    public function uploadCfdMedia(Request $request): JsonResponse
    {
        $this->authorize('create', Terminal::class);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        $path = $request->file('image')->store('cfd', 'public');

        return response()->json([
            'ok'   => true,
            'path' => $path,
            'url'  => \Illuminate\Support\Facades\Storage::disk('public')->url($path),
        ]);
    }

    public function export(Request $request, ExportTerminals $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', Terminal::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Unsupported export format. Use csv or xlsx.');
        }

        return ($export)($format, current_store_id() ?: default_store_id());
    }

    /* ── Helpers ────────────────────────────────────────────────── */

    /**
     * Terminals for the given store, newest first. A null store id (no
     * store exists yet) yields an empty set rather than every store's
     * terminals — the page is storewise, never a company-wide list.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Terminal>
     */
    private function orderedTerminals(?int $storeId): \Illuminate\Database\Eloquent\Collection
    {
        return Terminal::query()
            ->with('store:id,name')
            ->when($storeId, fn ($q) => $q->forStore($storeId), fn ($q) => $q->whereRaw('1 = 0'))
            ->ordered()
            ->get();
    }

    /** Build the JSON the client uses to refresh the list after a save/delete. */
    private function freshListJson(?int $selectedId, string $message): JsonResponse
    {
        $terminals = $this->orderedTerminals(current_store_id() ?: default_store_id());

        $listHtml = view('admin.terminals._list', ['terminals' => $terminals])->render();

        return response()->json([
            'ok'        => true,
            'message'   => $message,
            'id'        => $selectedId,
            'list_html' => $listHtml,
            'rows'      => $this->rowsPayload($terminals)->all(),
        ]);
    }
}
