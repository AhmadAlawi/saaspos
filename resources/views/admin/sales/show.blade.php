<x-admin-layout
    active="sales"
    :title="$sale->number"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('sales.title'), 'href' => route('admin.sales.index')],
        ['label' => $sale->number],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.sales.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('sales.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">
                        <span class="mono">{{ $sale->number }}</span>
                        @if ($sale->isVoided())
                            <span class="prod-head-badge prod-head-badge-muted">{{ __('sales.statuses.voided') }}</span>
                        @elseif ($sale->status === \App\Models\Sale::STATUS_PARTIALLY_REFUNDED)
                            <span class="prod-head-badge prod-head-badge-warn">{{ __('sales.statuses.partially_refunded') }}</span>
                        @elseif ($sale->status === \App\Models\Sale::STATUS_REFUNDED)
                            <span class="prod-head-badge prod-head-badge-muted">{{ __('sales.statuses.refunded') }}</span>
                        @endif
                    </h1>
                    <p class="page-sub">
                        {{ $sale->store?->name }} ·
                        {{ format_datetime($sale->sale_datetime) }} ·
                        {{ $sale->cashier?->name ?? '—' }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2" x-data>
                {{-- Refund button — visible only for completed /
                     partially-refunded sales AND only to users with
                     the `sales.refund` permission (via SalePolicy). --}}
                @can ('refund', $sale)
                    @if (in_array($sale->status, [\App\Models\Sale::STATUS_COMPLETED, \App\Models\Sale::STATUS_PARTIALLY_REFUNDED], true))
                        <a href="{{ route('admin.sales.refund.form', $sale) }}" class="pos-btn pos-btn-sm pos-btn-primary">
                            <x-icon name="refund" class="w-4 h-4" />
                            {{ __('sales.actions.refund') }}
                        </a>
                    @endif
                @endcan
                {{-- Void button — only when the sale is voidable: status=completed,
                     no refunds against it, and (if there's a shift) the shift is
                     still open. Reverses stock + customer balance; the cash refund
                     itself is physical (cashier hands cash back from the drawer). --}}
                @can ('void', $sale)
                    @php
                        $shiftOpen = $sale->shift_id ? (\App\Models\Shift::query()->find($sale->shift_id)?->isOpen() ?? false) : true;
                        $voidable  = $sale->status === \App\Models\Sale::STATUS_COMPLETED
                            && $sale->returns->isEmpty()
                            && $shiftOpen;
                    @endphp
                    @if ($voidable)
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger"
                                @click="$store.confirm.show({
                                    title:        {{ \Illuminate\Support\Js::from(__('sales.confirm_void.title', ['number' => $sale->number])) }},
                                    message:      {{ \Illuminate\Support\Js::from(__('sales.confirm_void.message')) }},
                                    intent:       'danger',
                                    confirmLabel: {{ \Illuminate\Support\Js::from(__('sales.confirm_void.confirm')) }},
                                    cancelLabel:  {{ \Illuminate\Support\Js::from(__('sales.confirm_void.cancel')) }},
                                    onConfirm:    () => $submitForm({{ \Illuminate\Support\Js::from(route('admin.sales.void', $sale)) }}),
                                })">
                            <x-icon name="x" class="w-4 h-4" />
                            {{ __('sales.actions.void') }}
                        </button>
                    @endif
                @endcan
                <x-print-button :sale="$sale" />
                <a href="{{ route('admin.sales.receipt', $sale) }}" target="_blank"
                   class="pos-btn pos-btn-sm pos-btn-ghost" title="{{ __('sales.actions.view_receipt') }}"
                   aria-label="{{ __('sales.actions.view_receipt') }}">
                    <x-icon name="external" class="w-4 h-4" />
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            {{-- LEFT: items + totals --}}
            <div class="space-y-5">
                <div class="card card-pad-0">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('sales.sections.items') }}</div>
                    </div></div>
                    <div class="dt-scroll">
                    <table class="dt-table">
                        <thead>
                            <tr>
                                <th>{{ __('sales.items.product') }}</th>
                                <th class="num">{{ __('sales.items.qty') }}</th>
                                <th class="num">{{ __('sales.items.unit_price') }}</th>
                                <th class="num">{{ __('sales.items.tax') }}</th>
                                <th class="num">{{ __('sales.items.line_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sale->items as $item)
                                <tr>
                                    <td>
                                        <div>{{ $item->product_name_snapshot }}</div>
                                        <div class="text-[11.5px] fg-tertiary mono">
                                            <span>{{ $item->sku_snapshot ?: '—' }}</span>
                                            @if ($item->batch)
                                                <span class="ms-2">· Batch {{ $item->batch->batch_number }}</span>
                                                @if ($item->batch->expiry_date)
                                                    <span class="ms-1">· Exp {{ $item->batch->expiry_date->toDateString() }}</span>
                                                @endif
                                            @endif
                                        </div>
                                        {{-- Kit components — listed inline so the
                                             admin / cashier can see what's inside
                                             the bundle without opening the product.
                                             Snapshot from the LIVE product since
                                             kits aren't snapshotted at sale time. --}}
                                        @if ($item->product && $item->product->type === 'kit' && $item->product->kitItems->isNotEmpty())
                                            <ul class="text-[11.5px] fg-tertiary mt-1 ps-3 list-disc">
                                                @foreach ($item->product->kitItems as $k)
                                                    <li>
                                                        {{ rtrim(rtrim((string) $k->quantity, '0'), '.') }}×
                                                        {{ $k->component?->name ?: '—' }}
                                                        @if ($k->variant?->label)
                                                            <span class="fg-tertiary">· {{ $k->variant->label }}</span>
                                                        @endif
                                                        @if ($k->variant?->sku ?: $k->component?->sku)
                                                            <span class="mono">({{ $k->variant?->sku ?: $k->component->sku }})</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                    <td class="num tnum">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }} {{ $item->unit }}</td>
                                    <td class="num tnum">{{ format_money($item->unit_price) }}</td>
                                    <td class="num tnum">
                                        {{ format_money($item->tax_amount) }}
                                        {{-- "(incl.)" suffix when the tax was extracted from
                                             the unit price rather than added on top — pulled
                                             from the line's TaxBreakdown snapshot so it stays
                                             correct even if the group's inclusive flag is
                                             flipped later. --}}
                                        @if (! empty($item->tax_breakdown['is_inclusive']) && (float) $item->tax_amount > 0)
                                            <span class="fg-tertiary italic text-[11px]">{{ __('sales.totals.tax_incl_suffix') }}</span>
                                        @endif
                                    </td>
                                    <td class="num tnum font-medium">
                                        {{-- Gross line total (no discount baked in).
                                             `line_total` stored on the row is post-
                                             discount; the order-level discount lives
                                             in its own row in the Totals card, so
                                             showing the gross here keeps the two
                                             figures from looking like they're
                                             cancelling each other out. --}}
                                        {{ format_money(bcadd((string) $item->line_total, (string) $item->discount_amount, 4)) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>

                @if ($sale->payments->isNotEmpty())
                    @php $canChangePaymentMethod = ! $sale->isVoided() && \Illuminate\Support\Facades\Gate::allows('changePaymentMethod', $sale); @endphp
                    <div class="card card-pad-0" x-data="{ editing: null }">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('sales.sections.payments') }}</div>
                        </div></div>
                        <div class="dt-scroll">
                        <table class="dt-table">
                            <thead>
                                <tr>
                                    <th>{{ __('sales.payments.method') }}</th>
                                    <th>{{ __('sales.payments.reference') }}</th>
                                    <th>{{ __('sales.payments.paid_at') }}</th>
                                    <th class="num">{{ __('sales.payments.tendered') }}</th>
                                    <th class="num">{{ __('sales.payments.amount') }}</th>
                                    @if ($canChangePaymentMethod)
                                        <th>{{ __('sales.payments.actions') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sale->payments as $p)
                                    <tr>
                                        <td>{{ $p->paymentMethod?->name ?: '—' }}</td>
                                        <td class="mono fg-tertiary">{{ $p->reference ?: '—' }}</td>
                                        <td>{{ format_datetime($p->paid_at) }}</td>
                                        <td class="num tnum">{{ $p->tendered_amount ? format_money($p->tendered_amount) : '—' }}</td>
                                        <td class="num tnum font-medium">{{ format_money($p->amount) }}</td>
                                        @if ($canChangePaymentMethod)
                                            <td>
                                                <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                                                        @click="editing = editing === {{ $p->id }} ? null : {{ $p->id }}">
                                                    {{ __('sales.payments.change_method') }}
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                    @if ($canChangePaymentMethod)
                                        <tr x-show="editing === {{ $p->id }}" x-cloak>
                                            <td colspan="6" class="bg-subtle">
                                                <form method="POST"
                                                      action="{{ route('admin.sales.payments.change-method', [$sale, $p]) }}"
                                                      data-ajax-form
                                                      class="flex flex-wrap items-end gap-2 py-1">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label class="field" style="margin-bottom:0;">
                                                        <span class="field-label is-required">{{ __('sales.payments.method') }}</span>
                                                        <select name="payment_method_id" class="pos-input" required>
                                                            @foreach ($paymentMethods as $m)
                                                                <option value="{{ $m->id }}" @selected($m->id === $p->payment_method_id)>{{ $m->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <label class="field" style="margin-bottom:0; flex:1; min-width:220px;">
                                                        <span class="field-label is-required">{{ __('sales.payments.reason') }}</span>
                                                        <input type="text" name="reason" class="pos-input" maxlength="255" required
                                                               placeholder="{{ __('sales.payments.reason_placeholder') }}">
                                                    </label>
                                                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                                                        {{ __('sales.payments.save') }}
                                                    </button>
                                                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="editing = null">
                                                        {{ __('sales.payments.cancel') }}
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    </div>
                @endif

                @if ($sale->notes)
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('sales.sections.notes') }}</div>
                        </div></div>
                        <div class="card-body">
                            <p class="text-sm whitespace-pre-wrap">{{ $sale->notes }}</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- RIGHT: totals + customer --}}
            <div class="space-y-5">
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('sales.sections.totals') }}</div>
                    </div></div>
                    <div class="card-body">
                        @php
                            // Pre-discount gross = sum of (qty × unit_price)
                            // across every line. The Sale row stores the
                            // POST-discount net subtotal (+ tax for inclusive
                            // lines via taxableAmount), so we add the
                            // discount and tax totals back to land on the
                            // customer-facing "gross qty × price" figure.
                            // With this, the totals block reads:
                            //   Subtotal − Discount = Grand total
                            // exactly, matching the cashier cart.
                            $grossSubtotal = bcadd(
                                bcadd((string) $sale->subtotal, (string) $sale->tax_total, 4),
                                (string) $sale->discount_total,
                                4
                            );
                        @endphp
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div><dt class="fg-tertiary">{{ __('sales.totals.subtotal') }}</dt><dd class="num tnum">{{ format_money($grossSubtotal) }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('sales.totals.discount') }}</dt><dd class="num tnum">{{ format_money($sale->discount_total) }}</dd></div>
                            @if ($sale->saleDiscount?->reason_category || $sale->saleDiscount?->reason || $sale->discount_approved_by)
                                <div class="col-span-2 -mt-2">
                                    <dd class="text-[12px] fg-tertiary">
                                        @if ($sale->saleDiscount?->reason_category)
                                            {{ __('cashier.discount.reason_categories.'.$sale->saleDiscount->reason_category) }}
                                        @endif
                                        @if ($sale->saleDiscount?->reason)
                                            <span class="fg-secondary">· "{{ $sale->saleDiscount->reason }}"</span>
                                        @endif
                                        @if ($sale->discountApprover)
                                            <span class="block">{{ __('sales.discount_audit.approved_by', ['name' => $sale->discountApprover->name]) }}</span>
                                        @endif
                                    </dd>
                                </div>
                            @endif
                            <div class="col-span-2"><dt class="fg-tertiary">{{ __('sales.totals.tax_already_included') }}</dt><dd class="num tnum">{{ format_money($sale->tax_total) }}</dd></div>
                            <div class="col-span-2 border-t border-subtle pt-3"><dt class="font-semibold">{{ __('sales.totals.grand') }}</dt><dd class="num tnum text-base font-semibold">{{ format_money($sale->grand_total) }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('sales.totals.paid') }}</dt><dd class="num tnum">{{ format_money($sale->paid_total) }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('sales.totals.change') }}</dt><dd class="num tnum">{{ format_money($sale->change_returned) }}</dd></div>
                            @if (bccomp((string) $sale->balance_due, '0', 4) > 0)
                                <div class="col-span-2 border-t border-subtle pt-3">
                                    <dt class="font-semibold text-amber-600 dark:text-amber-400">{{ __('sales.totals.balance_due') }}</dt>
                                    <dd class="num tnum text-base font-semibold text-amber-600 dark:text-amber-400">{{ format_money($sale->balance_due) }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                @if (bccomp((string) $sale->balance_due, '0', 4) > 0)
                    <div class="card" x-data="{ open: false }">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('sales.sections.record_payment') }}</div>
                            <div class="card-title-sub">{{ __('sales.payment_form.sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <form method="POST"
                                  action="{{ route('admin.sales.payments.store', $sale) }}"
                                  data-ajax-form>
                                @csrf
                                <div class="form-stack">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('sales.payment_form.method') }}</span>
                                        <select name="payment_method_id" class="pos-input" required
                                                x-data="enhancedSelect()">
                                            @foreach ($paymentMethods as $m)
                                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('sales.payment_form.amount') }}</span>
                                        <input type="number"
                                               name="amount"
                                               class="pos-input"
                                               step="0.0001"
                                               min="0.0001"
                                               max="{{ $sale->balance_due }}"
                                               value="{{ $sale->balance_due }}"
                                               required>
                                        <p class="field-help">{{ __('sales.payment_form.amount_help', ['balance' => format_money($sale->balance_due)]) }}</p>
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('sales.payment_form.reference') }}</span>
                                        <input type="text" name="reference" class="pos-input" maxlength="255">
                                    </label>
                                </div>
                                <div class="flex items-center justify-end gap-2 mt-4">
                                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                                        <x-icon name="check" class="w-4 h-4" />
                                        {{ __('sales.payment_form.submit') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- Refund history — shows once at least one refund
                     has been processed against this sale. --}}
                @if ($sale->returns->isNotEmpty())
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('sales.sections.refunds') }}</div>
                        </div></div>
                        <ul class="divide-y divide-subtle">
                            @foreach ($sale->returns as $r)
                                <li class="px-4 py-3 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-medium mono">{{ $r->number }}</div>
                                        <div class="fg-tertiary text-xs mt-0.5">
                                            {{ format_date($r->return_date) }} ·
                                            {{ $r->reason?->name ?? '—' }}
                                        </div>
                                        @if ($r->gateway_refund_status === \App\Models\SaleReturn::GATEWAY_REFUND_SUCCEEDED)
                                            <span class="prod-badge prod-badge-positive mt-1">{{ __('sales.gateway_refund.reversed') }}</span>
                                        @elseif ($r->gateway_refund_status === \App\Models\SaleReturn::GATEWAY_REFUND_FAILED)
                                            <div class="mt-1 flex items-center gap-2 flex-wrap">
                                                <span class="prod-badge prod-badge-negative" title="{{ $r->gateway_refund_error }}">{{ __('sales.gateway_refund.failed') }}</span>
                                                @can ('refund', $sale)
                                                    <button type="button"
                                                            x-data="{ busy: false }"
                                                            :disabled="busy"
                                                            class="pos-btn pos-btn-xs pos-btn-ghost"
                                                            @click="busy = true; $http.post({{ \Illuminate\Support\Js::from(route('admin.sales.returns.retry-reversal', $r)) }}, {})
                                                                        .then(() => window.location.reload())
                                                                        .catch((e) => { busy = false; $store.toasts.push({ type: 'error', message: e?.message || {{ \Illuminate\Support\Js::from(__('sales.gateway_refund.retry_failed')) }} }); })">
                                                        {{ __('sales.gateway_refund.retry') }}
                                                    </button>
                                                @endcan
                                            </div>
                                        @endif
                                    </div>
                                    <div class="num tnum text-sm font-medium text-right">−{{ format_money($r->grand_total) }}</div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('sales.sections.customer') }}</div>
                    </div></div>
                    <div class="card-body text-sm">
                        @if ($sale->customer)
                            <div class="font-medium">{{ $sale->customer->name }}</div>
                            @if ($sale->customer->phone)
                                <div class="fg-tertiary mono">{{ \App\Support\PhoneFormatter::pretty($sale->customer->phone) }}</div>
                            @endif
                            @if ($sale->customer->email)
                                <div class="fg-tertiary">{{ $sale->customer->display_email }}</div>
                            @endif
                            @if (bccomp((string) ($sale->customer->outstanding_balance ?? '0'), '0', 4) > 0)
                                <div class="mt-3 pt-3 border-t border-subtle">
                                    <div class="fg-tertiary text-xs">{{ __('sales.customer.outstanding_total') }}</div>
                                    <div class="font-semibold text-amber-600 dark:text-amber-400 num tnum">
                                        {{ format_money($sale->customer->outstanding_balance) }}
                                    </div>
                                </div>
                            @endif
                        @else
                            <p class="fg-tertiary">{{ __('sales.walk_in') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
