<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Sales\ReleaseHeldReservation;
use App\Http\Controllers\Concerns\RendersDataTableRows;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\Excel\SpreadsheetWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Staff orders queue — the pending self-ordering kiosk orders
 * (`sales.status = placed`) awaiting counter payment. See
 * docs/features/kiosk-self-ordering.md §5 (order mode).
 *
 * Accept pulls an order into the till for payment: it flips the placed order
 * to a HELD ticket (keeping its stock reservation) and sends the operator to
 * the cashier, where they resume it from the Held drawer and ring it up
 * through the normal completion path. Reject releases the reservation and
 * voids the order.
 */
class KioskOrderController extends Controller
{
    use RendersDataTableRows;

    /**
     * The pending kiosk-order queue (placed, awaiting counter payment), scoped
     * to the active store, oldest first. Shared by the inline first page and
     * the paginated {@see rows()} endpoint.
     *
     * @return Builder<Sale>
     */
    private function pendingQuery(int $storeId): Builder
    {
        return Sale::query()
            ->placed()
            ->forStore($storeId)
            ->with([
                'items',
                'customer:id,name,phone',
                // The shopper scanned the kiosk's static UPI QR and said they
                // paid. Unverified — staff check their own UPI app before
                // settling. See docs/features/kiosk-self-ordering.md §5.
                'kioskPaymentClaimMethod:id,name',
            ])
            ->withCount('items')
            ->orderBy('placed_at')
            ->orderBy('id');
    }

    /**
     * The effective date window + filters for the All-orders tab. Defaults to
     * TODAY — the pending/collect queues only ever show open work, so this tab
     * is where a paid, collected or rejected order goes to be found again, and
     * "what came through the kiosk today" is the question staff actually ask.
     * An unbounded default would page through the whole kiosk history.
     *
     * @return array{from:string, to:string, status:string, q:string}
     */
    private function allFilters(Request $request): array
    {
        $today = now()->toDateString();

        return [
            'from'   => (string) ($request->query('from') ?: $today),
            'to'     => (string) ($request->query('to') ?: $today),
            'status' => (string) $request->query('status', ''),
            'q'      => trim((string) $request->query('q', '')),
        ];
    }

    /**
     * Every kiosk order, whatever became of it — placed, held at the till,
     * paid, collected, voided. Keyed on `origin` (which survives payment)
     * rather than status, and windowed by date. Store-scoped like the queues.
     *
     * @return Builder<Sale>
     */
    private function allQuery(Request $request, int $storeId): Builder
    {
        $f = $this->allFilters($request);

        $validStatuses = [
            Sale::STATUS_PLACED,
            Sale::STATUS_HELD,
            Sale::STATUS_COMPLETED,
            Sale::STATUS_VOIDED,
            Sale::STATUS_PARTIALLY_REFUNDED,
            Sale::STATUS_REFUNDED,
        ];

        return Sale::query()
            ->where('origin', 'kiosk')
            ->forStore($storeId)
            // `sale_datetime` rather than `placed_at`: paying an order replaces
            // the placed row with a fresh completed sale that carries no
            // placed_at, so it's the one timestamp every kiosk order has.
            ->whereDate('sale_datetime', '>=', $f['from'])
            ->whereDate('sale_datetime', '<=', $f['to'])
            ->when(in_array($f['status'], $validStatuses, true), fn ($qb) => $qb->where('status', $f['status']))
            ->when($f['q'] !== '', fn ($qb) => $qb->where(function ($w) use ($f) {
                $w->where('number', 'like', "%{$f['q']}%")
                  ->orWhere('pickup_code', 'like', "%{$f['q']}%");
            }))
            ->with(['customer:id,name,phone'])
            ->withCount('items')
            ->orderByDesc('sale_datetime')
            ->orderByDesc('id');
    }

    /** One page of all-kiosk-order rows as an HTML fragment. */
    public function allRows(Request $request): JsonResponse
    {
        abort_unless($this->canView($request), 403);

        $storeId   = current_store_id() ?: default_store_id();
        $paginator = $this->allQuery($request, (int) $storeId)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.kiosk-orders._all_rows', 'allOrders');
    }

    public function index(Request $request): View
    {
        abort_unless($this->canView($request), 403);

        $storeId = current_store_id() ?: default_store_id();

        // Pending queue — server-paginated. Only the first page renders inline;
        // paging round-trips to rows(). The take-payment panel and the collect
        // queue below are unchanged.
        $perPage       = $this->dtPerPage($request);
        $pendingPage   = $this->pendingQuery((int) $storeId)->paginate($perPage);
        $orders        = $pendingPage->getCollection();
        $pendingTotal  = $pendingPage->total();

        // Kiosk sales already PAID at the machine. They never entered this queue
        // before — a customer holding pickup code "K007" had nothing staff could
        // look up. They stay listed until someone hands the bag over.
        $awaitingCollection = Sale::query()
            ->awaitingCollection()
            ->forStore((int) $storeId)
            // Items + tenders ride along so the handover modal opens instantly
            // and the cashier never leaves the queue to check what's in the bag.
            ->with([
                'customer:id,name,phone',
                'items',
                'payments.paymentMethod:id,name',
            ])
            ->withCount('items')
            ->orderBy('sale_datetime')
            ->get();

        // All kiosk orders, whatever their fate — the history the two queues
        // above can't show, because an order leaves them the moment it's paid,
        // collected or rejected. Server-paginated, defaults to today.
        $allPage = $this->allQuery($request, (int) $storeId)->paginate($perPage);

        $storeName = \App\Models\Store::query()->whereKey($storeId)->value('name');

        return view('admin.kiosk-orders.index', [
            'orders' => $orders,
            'pendingTotal'      => $pendingTotal,
            'pendingPerPage'    => $perPage,
            'pendingTotalPages' => max(1, $pendingPage->lastPage()),
            'awaitingCollection' => $awaitingCollection,
            'allOrders'         => $allPage->getCollection(),
            'allTotal'          => $allPage->total(),
            'allPerPage'        => $perPage,
            'allTotalPages'     => max(1, $allPage->lastPage()),
            'allFilters'        => $this->allFilters($request),
            // Counter tenders. Gateway-backed methods are hidden here for the
            // same reason the cashier hides them: they're reached through the
            // single "Charge via QR" flow, never rung up by hand.
            'paymentMethods' => \App\Models\PaymentMethod::query()
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('provider')->orWhere('provider', '')->orWhere('provider', 'none'))
                ->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'code', 'name', 'type', 'requires_reference', 'provider_credentials'])
                ->map(function (\App\Models\PaymentMethod $m) use ($storeName) {
                    // UPI is a manual-confirm tender, not a gateway: we render a
                    // `upi://pay?…` QR from the stored VPA exactly as the cashier
                    // does, and the counter types the UTR as the reference.
                    $creds = $m->provider_credentials ?? [];

                    return [
                        'id'                 => (string) $m->id,
                        'code'               => $m->code,
                        'name'               => $m->name,
                        'type'               => $m->type,
                        'requires_reference' => (bool) $m->requires_reference,
                        'vpa'                => $creds['vpa'] ?? null,
                        // Payee name shown in the customer's UPI app: an explicit
                        // per-method override, else the outlet's own name.
                        'payee_name'         => $creds['payee_name'] ?? ($storeName ?: config('app.name')),
                    ];
                })->values(),
            // Is any online gateway configured? Drives the "Charge via QR" tile.
            'hasGateway' => \App\Models\PaymentMethod::query()
                ->where('is_active', true)
                ->whereNotNull('provider')
                ->whereNotIn('provider', ['', 'none'])
                ->exists(),
        ]);
    }

    /**
     * One page of pending kiosk-order rows as an HTML fragment. The rows carry
     * the inline take-payment payload (built in the partial), so paging never
     * needs a second round-trip to open the panel. See {@see RendersDataTableRows}.
     */
    public function rows(Request $request): JsonResponse
    {
        abort_unless($this->canView($request), 403);

        $storeId   = current_store_id() ?: default_store_id();
        $paginator = $this->pendingQuery((int) $storeId)
            ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));

        return $this->dtRows($paginator, 'admin.kiosk-orders._pending_rows', 'orders');
    }

    /**
     * Take payment for a pending kiosk order — without leaving this page.
     *
     * The panel may have edited the order at the counter (dropped a line,
     * changed a quantity), so we rebuild the cart from the order's OWN stored
     * items and apply only the quantity edits the client sent. Prices are never
     * taken from the client.
     *
     * Then it's exactly a cashier ring-up: release the reservation, drop the
     * pending row, and hand the cart to {@see CompleteSale}, which owns stock,
     * tax, payments, numbering, the journal and the receipt link. `origin` and
     * `pickup_code` ride along so the completed sale keeps its kiosk provenance.
     */
    public function pay(Request $request, Sale $sale, \App\Actions\Sales\CompleteSale $complete): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('sales.create'), 403);

        if (! $sale->isPlaced()) {
            return response()->json(['message' => __('kiosk-orders.errors.not_pending')], 422);
        }

        $data = $request->validate([
            'local_uuid'                     => ['nullable', 'string', 'max:36'],
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.sale_item_id'           => ['required', 'integer'],
            'items.*.quantity'               => ['required', 'numeric', 'gt:0'],
            'payments'                       => ['required', 'array', 'min:1', 'max:10'],
            'payments.*.payment_method_id'   => ['required', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount'              => ['required', 'numeric', 'gt:0'],
            'payments.*.tendered_amount'     => ['nullable', 'numeric', 'gte:0'],
            'payments.*.reference'           => ['nullable', 'string', 'max:191'],
            'payments.*.gateway_provider'    => ['nullable', 'string', 'max:32'],
            'payments.*.gateway_payment_id'  => ['nullable', 'string', 'max:191'],
            'payments.*.gateway_status'      => ['nullable', 'string', 'max:32'],
        ]);

        $sale->load('items');

        // Rebuild the cart from OUR rows, keyed by sale_item_id. Anything the
        // client omitted was removed at the counter; prices stay ours.
        $wanted = collect($data['items'])->keyBy(fn ($i) => (int) $i['sale_item_id']);
        $lines  = $sale->items
            ->filter(fn ($item) => $wanted->has((int) $item->id))
            ->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'variant_id' => $item->variant_id,
                'quantity'   => (string) $wanted[(int) $item->id]['quantity'],
                'unit_price' => (string) $item->unit_price,
            ])->values()->all();

        if (empty($lines)) {
            return response()->json(['message' => __('kiosk-orders.errors.no_lines')], 422);
        }

        $pickupCode = $sale->pickup_code;
        $kioskNote  = $sale->kiosk_note;
        $customerId = $sale->customer_id;
        $currency   = $sale->currency_code;
        $storeId    = (int) $sale->store_id;

        try {
            $completed = \Illuminate\Support\Facades\DB::transaction(function () use (
                $sale, $lines, $data, $complete, $storeId, $customerId, $currency, $pickupCode, $kioskNote, $request
            ) {
                // Hand back the units this order had reserved, then retire the
                // pending row so it can never be paid twice. If CompleteSale
                // throws (stock gone), the whole thing rolls back and the order
                // is still sitting in the queue.
                $this->releaseReservation($sale);
                $sale->items()->delete();
                $sale->forceDelete();

                return $complete(
                    [
                        'store_id'      => $storeId,
                        'customer_id'   => $customerId,
                        'currency_code' => $currency,
                        'origin'        => 'kiosk',
                        'pickup_code'   => $pickupCode,
                        'kiosk_note'    => $kioskNote,
                        'local_uuid'    => $data['local_uuid'] ?? null,
                    ],
                    $lines,
                    array_values($data['payments']),
                    $request->user(),
                );
            });
        } catch (\App\Exceptions\InsufficientStock $e) {
            return response()->json([
                'message' => __('sales.errors.insufficient_stock', [
                    'name' => $e->productName, 'available' => $e->available, 'requested' => $e->requested,
                ]),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'          => true,
            'number'      => $completed->number,
            'grand_total' => (string) $completed->grand_total,
            'receipt_url' => $completed->publicReceiptUrl(),
            'message'     => __('kiosk-orders.flash.paid', ['code' => $pickupCode, 'number' => $completed->number]),
        ]);
    }

    /** Give back the units a placed order had reserved. Floors at zero. */
    private function releaseReservation(Sale $sale): void
    {
        foreach ($sale->items as $item) {
            $level = \App\Models\StockLevel::query()
                ->where('store_id', $sale->store_id)
                ->where('product_id', $item->product_id)
                ->where('variant_id', $item->variant_id)
                ->lockForUpdate()
                ->first();
            if (! $level) {
                continue;
            }
            $next = bcsub((string) $level->reserved_quantity, (string) $item->quantity, 4);
            $level->forceFill([
                'reserved_quantity' => bccomp($next, '0', 4) < 0 ? '0.0000' : $next,
            ])->save();
        }
    }

    /**
     * Reject an order — release its stock reservation and void it (kept for
     * audit, not deleted).
     */
    public function reject(Request $request, Sale $sale, ReleaseHeldReservation $release): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('sales.void'), 403);
        abort_unless($sale->isPlaced(), 404);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // ReleaseHeldReservation only runs on HELD; flip to held first so the
        // reservation is given back through the shared, idempotent path, then
        // void.
        $sale->forceFill(['status' => Sale::STATUS_HELD])->save();
        $release($sale->load('items'));

        $sale->forceFill([
            'status'      => Sale::STATUS_VOIDED,
            'voided_at'   => now(),
            'voided_by'   => $request->user()?->id,
            'void_reason' => $data['reason'] ?? __('kiosk-orders.reject_default_reason'),
        ])->save();

        return redirect()->route('admin.kiosk-orders.index')
            ->with('success', __('kiosk-orders.flash.rejected', ['code' => $sale->pickup_code]));
    }

    public function export(Request $request, SpreadsheetWriter $writer): StreamedResponse
    {
        abort_unless($this->canView($request), 403);

        $storeId = current_store_id() ?: default_store_id();

        $orders = Sale::query()
            ->placed()
            ->forStore((int) $storeId)
            ->with('customer:id,name,phone')
            ->withCount('items')
            ->orderBy('placed_at')
            ->get();

        $format   = strtolower((string) $request->query('format', 'csv')) === 'xlsx' ? 'xlsx' : 'csv';
        $filename = 'kiosk-orders-'.now()->format('Ymd-His').'.'.$format;

        $header = [
            __('kiosk-orders.export.pickup'),
            __('kiosk-orders.export.number'),
            __('kiosk-orders.export.placed_at'),
            __('kiosk-orders.export.customer'),
            __('kiosk-orders.export.items'),
            __('kiosk-orders.export.total'),
            __('kiosk-orders.export.note'),
        ];

        return $writer->stream($filename, $header, function (callable $write) use ($orders) {
            $write($orders->map(fn (Sale $o) => [
                $o->pickup_code,
                $o->number,
                optional($o->placed_at)->toDateTimeString(),
                $o->customer?->name ?? __('kiosk-orders.walk_in'),
                (string) $o->items_count,
                (string) $o->grand_total,
                $o->kiosk_note,
            ])->all());
        });
    }

    private function canView(Request $request): bool
    {
        $u = $request->user();

        return (bool) ($u?->hasPermission('sales.view_all') || $u?->hasPermission('sales.view_own'));
    }

    /**
     * Staff handed the goods to the customer holding this pickup code.
     *
     * The sale is already paid and completed — nothing about the money moves.
     * We only stamp when it left the counter, which drops it off the
     * awaiting-collection list. Idempotent: a double-click keeps the first
     * timestamp rather than rewriting history.
     */
    public function collect(Request $request, Sale $sale): RedirectResponse
    {
        abort_unless($this->canView($request), 403);
        abort_unless($sale->origin === 'kiosk' && $sale->isCompleted() && $sale->pickup_code, 404);
        enforce_store_access((int) $sale->store_id);

        if (! $sale->kiosk_collected_at) {
            $sale->forceFill(['kiosk_collected_at' => now()])->save();
        }

        return redirect()->route('admin.kiosk-orders.index')
            ->with('success', __('kiosk-orders.flash.collected', ['code' => $sale->pickup_code]));
    }
}
