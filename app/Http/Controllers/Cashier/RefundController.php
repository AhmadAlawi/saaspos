<?php

namespace App\Http\Controllers\Cashier;

use App\Actions\Sales\RecordBlindReturn;
use App\Actions\Sales\RecordSaleReturn;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RefundSaleRequest;
use App\Models\PaymentMethod;
use App\Models\ReturnReason;
use App\Models\Sale;
use App\Models\Store;
use App\Services\Sales\RefundApprovalToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Cashier-surface refund flow — three thin JSON endpoints that drive
 * the cashier's refund modal. The business logic (qty caps, tax
 * proration, stock reversal, status flips, numbering) all stays in
 * {@see RecordSaleReturn} — these methods just shape the request +
 * response for the cashier-facing UI.
 *
 *   GET  /cashier/refund/lookup?q=…
 *       Search by sale number / receipt; falls back to the 20 most
 *       recent completed/partially-refunded sales in the active store
 *       when the query is empty (so the cashier always sees something
 *       on first open).
 *
 *   GET  /cashier/refund/{sale}
 *       Returns the sale + items + remaining returnable qty + reason
 *       + method picklists. Used to populate the refund modal once a
 *       sale has been picked from the lookup.
 *
 *   POST /cashier/refund/{sale}
 *       Records the refund via {@see RecordSaleReturn}.
 *
 * Permission split: browsing an invoice to pick refund lines only needs
 * `sales.create` (any real cashier) — the actual mutation (`store()`,
 * `storeBlind()`) needs `sales.refund` directly OR a `refund_approval`
 * token from a manager who has it (see {@see RefundApprovalController}).
 * A cashier without `sales.refund` can open the refund screen and build
 * up the lines to refund; submitting is what triggers the PIN prompt.
 */
class RefundController extends Controller
{
    use RespondsJsonOrRedirect;

    public function lookup(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('sales.create'), 403);

        $q       = trim((string) $request->query('q', ''));
        $storeId = $this->activeStoreId($request);

        $query = Sale::query()
            ->where('store_id', $storeId)
            ->whereIn('status', [Sale::STATUS_COMPLETED, Sale::STATUS_PARTIALLY_REFUNDED])
            ->orderByDesc('sale_datetime')
            ->limit(20);

        if ($q !== '') {
            // Match against sale number OR customer name. Sale number
            // matches loosely (so a cashier can type the tail of a
            // receipt — e.g. "0042").
            $query->where(function ($qb) use ($q) {
                $qb->where('number', 'like', '%'.$q.'%')
                   ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$q.'%'));
            });
        }

        $rows = $query->with('customer:id,name')->get()->map(fn (Sale $s) => [
            'id'             => $s->id,
            'number'         => $s->number,
            'sale_datetime'  => optional($s->sale_datetime)->toIso8601String(),
            'grand_total'    => (string) $s->grand_total,
            'status'         => $s->status,
            'customer'       => $s->customer?->name,
        ])->values();

        // Return the array directly to match the cashier customer-search
        // contract (the Alpine factory does `Array.isArray(data) ? data : []`).
        return response()->json($rows);
    }

    public function show(Request $request, Sale $sale): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('sales.create'), 403);

        if (! \in_array($sale->status, [Sale::STATUS_COMPLETED, Sale::STATUS_PARTIALLY_REFUNDED], true)) {
            return response()->json([
                'message' => __('sales.refund.not_refundable'),
                'errors'  => ['_action' => [__('sales.refund.not_refundable')]],
            ], 422);
        }

        $sale->load([
            'store:id,name,code',
            'customer:id,name,phone',
            'items.product:id,sku,name',
            'items.variant:id,sku',
            'payments.paymentMethod:id,code,name,type',
        ]);

        $items = $sale->items->map(function ($i) {
            $remaining = bcsub((string) $i->quantity, (string) $i->quantity_returned, 4);
            return [
                'id'              => $i->id,
                'name'            => $i->product_name_snapshot ?: $i->product?->name,
                'sku'             => $i->sku_snapshot ?: $i->product?->sku,
                'variant'         => $i->variant?->sku,
                'unit'            => $i->unit ?: 'pc',
                'quantity'        => (string) $i->quantity,
                'returned'        => (string) $i->quantity_returned,
                'remaining'       => $remaining,
                'unit_price'      => (string) $i->unit_price,
                'tax_amount'      => (string) $i->tax_amount,
                'discount_amount' => (string) ($i->discount_amount ?? '0'),
            ];
        })->values();

        // Refund tenders are limited to the methods actually used on the
        // sale (can't refund a card/gateway charge via a different method).
        // Unpaid credit sales have none → fall back to the full active list.
        $methods = $sale->refundMethods();
        if ($methods->isEmpty()) {
            $methods = PaymentMethod::query()->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name', 'type']);
        }

        // Default refund method follows the original tender, falling
        // back to cash. One-click full refund needs no edits.
        $originalMethodId = $sale->payments->first()?->payment_method_id;
        $cashMethodId     = PaymentMethod::query()->where('type', 'cash')->where('is_active', true)->value('id');

        return response()->json([
            'sale' => [
                'id'             => $sale->id,
                'number'         => $sale->number,
                'sale_datetime'  => optional($sale->sale_datetime)->toIso8601String(),
                'grand_total'    => (string) $sale->grand_total,
                'status'         => $sale->status,
                'customer'       => $sale->customer ? [
                    'id'    => $sale->customer->id,
                    'name'  => $sale->customer->name,
                    'phone' => demo_mask_phone($sale->customer->phone),
                ] : null,
            ],
            'items'           => $items,
            'reasons'         => ReturnReason::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'methods'         => $methods,
            'default_method_id' => $originalMethodId ?: $cashMethodId,
            // Tells the cashier UI whether to show the PIN-approval step
            // before submitting, or skip straight to it.
            'can_refund'      => (bool) $request->user()?->hasPermission('sales.refund'),
        ]);
    }

    public function store(RefundSaleRequest $request, Sale $sale, RecordSaleReturn $record, RefundApprovalToken $tokens): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('sales.create'), 403);
        $this->authorizeRefund($request, $sale, $tokens, $request->refundData($sale->id)['items']);

        try {
            $refund = $record(
                array_merge($request->refundData($sale->id), [
                    'client_uuid' => $request->input('client_uuid'),
                ]),
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return $this->jsonOrError($request, $e->getMessage());
        }

        return $this->jsonOrRedirect(
            $request,
            __('sales.refund.flash.created', ['number' => $refund->number]),
            route('cashier.index'),
            extra: [
                'refund' => [
                    'id'           => $refund->id,
                    'number'       => $refund->number,
                    'grand_total'  => (string) $refund->grand_total,
                    'sale_number'  => $sale->number,
                ],
            ],
        );
    }

    /**
     * A cashier without `sales.refund` may still submit a refund if they
     * carry a valid `refund_approval` token from a manager who has it —
     * capped at the refund total they were shown when the manager
     * approved (so the token can't be stretched onto a bigger refund
     * after the fact). Aborts 403 when neither check passes.
     *
     * @param array<int, array{sale_item_id:int, quantity:string}> $items
     */
    private function authorizeRefund(Request $request, Sale $sale, RefundApprovalToken $tokens, array $items): void
    {
        if ($request->user()?->hasPermission('sales.refund')) {
            return;
        }

        $total = $this->estimateRefundTotal($sale, $items);
        $approverId = $tokens->verify($request->input('refund_approval'), (int) $sale->store_id, $total);

        abort_unless($approverId !== null, 403, __('sales.refund.approval_required'));
    }

    /**
     * Rough refund total for the approval-token ceiling check — sums
     * `unit_price × quantity` for the submitted lines against the sale's
     * OWN item prices (never trusts a client-supplied amount). Doesn't
     * need to match {@see RecordSaleReturn}'s tax-prorated total to the
     * cent; it just has to be a faithful ceiling a manager actually saw.
     *
     * @param array<int, array{sale_item_id:int, quantity:string}> $items
     */
    private function estimateRefundTotal(Sale $sale, array $items): string
    {
        $ids  = array_column($items, 'sale_item_id');
        $rows = $sale->items()->whereIn('id', $ids)->get(['id', 'unit_price'])->keyBy('id');

        $total = '0';
        foreach ($items as $item) {
            $row = $rows->get((int) $item['sale_item_id']);
            if (! $row) {
                continue;
            }
            $total = bcadd($total, bcmul((string) $row->unit_price, (string) $item['quantity'], 4), 4);
        }

        return $total;
    }

    /**
     * "Blind" refund — a cashier scans a barcode with no original invoice
     * in hand. Always needs approval (the caller never holds
     * `sales.refund` for this path to make sense — a manager scanning
     * their own refund would just use the invoice-based flow), so this
     * doesn't check `hasPermission` at all, only the token.
     */
    public function storeBlind(Request $request, RecordBlindReturn $record, RefundApprovalToken $tokens): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('sales.create'), 403);

        $storeId = $this->activeStoreId($request);

        $data = $request->validate([
            'reason_code_id'   => ['required', 'integer', Rule::exists('return_reasons', 'id')->where('is_active', true)],
            'refund_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'restock'          => ['sometimes', 'boolean'],
            'notes'            => ['nullable', 'string', 'max:5000'],
            'client_uuid'      => ['nullable', 'string', 'max:64'],
            'items'                 => ['required', 'array', 'min:1', 'max:200'],
            'items.*.barcode'       => ['required', 'string', 'max:64'],
            'items.*.quantity'      => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price'    => ['required', 'numeric', 'gt:0'],
        ]);

        $total = '0';
        foreach ($data['items'] as $item) {
            $total = bcadd($total, bcmul((string) $item['unit_price'], (string) $item['quantity'], 4), 4);
        }

        $approverId = $tokens->verify($request->input('refund_approval'), $storeId, $total);
        abort_unless($approverId !== null, 403, __('sales.refund.approval_required'));

        try {
            $refund = $record(array_merge($data, [
                'store_id'    => $storeId,
                'client_uuid' => $data['client_uuid'] ?? null,
            ]), $request->user());
        } catch (RuntimeException $e) {
            return $this->jsonOrError($request, $e->getMessage());
        }

        return $this->jsonOrRedirect(
            $request,
            __('sales.refund.flash.created', ['number' => $refund->number]),
            route('cashier.index'),
            extra: [
                'refund' => [
                    'id'          => $refund->id,
                    'number'      => $refund->number,
                    'grand_total' => (string) $refund->grand_total,
                ],
            ],
        );
    }

    /**
     * Honour the session-active store (same key as the cashier index).
     * Falls back to the default / first active store so a fresh login
     * still finds something to scope the lookup against.
     */
    private function activeStoreId(Request $request): int
    {
        $sessionId = (int) $request->session()->get('active_store_id', 0);
        if ($sessionId > 0) return $sessionId;

        $store = Store::query()->where('is_active', true)->orderBy('id')->first();
        return $store?->id ?? 0;
    }
}
