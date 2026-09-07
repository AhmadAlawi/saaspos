<x-admin-layout
    active="purchases"
    :title="$purchase->number"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('purchases.crumb_parent')],
        ['label' => __('purchases.title'), 'href' => route('admin.purchases.index')],
        ['label' => $purchase->number],
    ]">

    @php
        $statusBadge = match ($purchase->status) {
            'draft', 'submitted', 'cancelled' => 'prod-badge-muted',
            default                            => 'prod-badge-positive',
        };
    @endphp

    <div class="page-wide">
        @php $priceChanges = session('price_changes'); @endphp
        @if (! empty($priceChanges))
            <div class="card card-accent-outline mb-5">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('purchases.price_changes.title', ['count' => count($priceChanges)]) }}</div>
                        <div class="card-title-sub">{{ __('purchases.price_changes.sub') }}</div>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="text-sm space-y-1">
                        @foreach ($priceChanges as $pc)
                            <li class="flex items-center gap-2">
                                <span class="mono">{{ $pc['sku'] }}</span>
                                <span class="fg-tertiary">·</span>
                                <span>{{ $pc['name'] }}</span>
                                <span class="ms-auto num tnum fg-tertiary">{{ format_money($pc['old_price']) }}</span>
                                <span class="fg-tertiary">→</span>
                                <span class="num tnum font-semibold">{{ format_money($pc['new_price']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.purchases.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('purchases.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">
                        {{ $purchase->number }}
                        <span class="prod-badge {{ $statusBadge }} ms-2">{{ __('purchases.status.'.$purchase->status) }}</span>
                    </h1>
                    <p class="page-sub">
                        {{ $purchase->supplier?->name }} ·
                        {{ format_date($purchase->purchase_date) }}
                        @if ($purchase->supplier_invoice_number)
                            · <span class="mono">{{ $purchase->supplier_invoice_number }}</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2" x-data>
                @if ($purchase->status === 'draft')
                    {{-- Cancel — pre-receive cancel is harmless (no stock, no journal). --}}
                    @can('update', $purchase)
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger"
                                @click="$store.confirm.show({
                                    title: {{ \Illuminate\Support\Js::from(__('purchases.confirm_cancel.title', ['number' => $purchase->number])) }},
                                    message: {{ \Illuminate\Support\Js::from(__('purchases.confirm_cancel.message')) }},
                                    intent: 'danger',
                                    confirmLabel: {{ \Illuminate\Support\Js::from(__('purchases.confirm_cancel.confirm')) }},
                                    onConfirm: () => $submitForm({{ \Illuminate\Support\Js::from(route('admin.purchases.cancel', $purchase)) }}),
                                })">
                            <x-icon name="x" class="w-4 h-4" />
                            {{ __('purchases.actions.cancel') }}
                        </button>
                    @endcan
                    @can('delete', $purchase)
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger"
                                @click="$store.confirm.show({
                                    title: {{ \Illuminate\Support\Js::from(__('purchases.confirm_delete.title', ['number' => $purchase->number])) }},
                                    message: {{ \Illuminate\Support\Js::from(__('purchases.confirm_delete.message')) }},
                                    intent: 'danger',
                                    confirmLabel: {{ \Illuminate\Support\Js::from(__('purchases.confirm_delete.confirm')) }},
                                    onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.purchases.destroy', $purchase)) }}),
                                })">
                            <x-icon name="trash" class="w-4 h-4" />
                            {{ __('purchases.actions.delete') }}
                        </button>
                    @endcan
                    @can('update', $purchase)
                        <a href="{{ route('admin.purchases.edit', $purchase) }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                            <x-icon name="edit" class="w-4 h-4" />
                            {{ __('purchases.actions.edit') }}
                        </a>
                    @endcan
                    {{-- Receive is the headline action — primary CTA. Confirmation
                         dialog is light (no per-line shortage capture yet); v1.0
                         forces received = ordered per the feature doc.

                         When at least one line carries a `markup_percent`, the
                         buyer gets an inline "Update X selling prices" checkbox
                         beside the button. Default state = company-level
                         "auto-apply markup on receive" setting. The decision
                         persists on `purchases.markup_applied` for audit. --}}
                    @can('receive', $purchase)
                        <div class="flex items-center gap-3"
                             x-data="{ applyMarkup: {{ $companyMarkupDefault ? 'true' : 'false' }} }">
                            @if ($hasMarkupEligibleLines)
                                <label class="field-toggle">
                                    <input type="checkbox" x-model="applyMarkup">
                                    <span>{{ __('purchases.receive.apply_markup_checkbox') }}</span>
                                </label>
                            @endif
                            <button type="button" class="pos-btn pos-btn-sm pos-btn-primary"
                                    @click="$store.confirm.show({
                                        title: {{ \Illuminate\Support\Js::from(__('purchases.confirm_receive.title', ['number' => $purchase->number])) }},
                                        message: {{ \Illuminate\Support\Js::from(__('purchases.confirm_receive.message')) }},
                                        intent: 'primary',
                                        confirmLabel: {{ \Illuminate\Support\Js::from(__('purchases.confirm_receive.confirm')) }},
                                        onConfirm: () => $submitForm({{ \Illuminate\Support\Js::from(route('admin.purchases.receive', $purchase)) }}, { apply_markup: applyMarkup ? '1' : '0' }),
                                    })">
                                <x-icon name="check" class="w-4 h-4" />
                                {{ __('purchases.actions.receive') }}
                            </button>
                        </div>
                    @endcan
                @elseif (in_array($purchase->status, ['received', 'partially_paid', 'paid'], true))
                    {{-- Post-receive: "Create return" secondary CTA + "Record payment" primary CTA. --}}
                    @can('update', $purchase)
                        <a href="{{ route('admin.purchase-returns.create', $purchase) }}"
                           class="pos-btn pos-btn-sm pos-btn-ghost">
                            <x-icon name="refund" class="w-4 h-4" />
                            {{ __('purchases.actions.create_return') }}
                        </a>
                    @endcan
                    @can('create', App\Models\PurchasePayment::class)
                        <a href="{{ route('admin.supplier-payments.create', [
                                'supplier_id' => $purchase->supplier_id,
                                // Pin the store + supplier to what this PO already
                                // chose — see SupplierPaymentController::create()
                                // which uses these to (a) preselect the store and
                                // (b) lock the supplier picker so the cashier can't
                                // accidentally book the payment under another vendor.
                                'store_id'    => $purchase->store_id,
                                'from'        => 'purchase',
                            ]) }}"
                           class="pos-btn pos-btn-sm pos-btn-primary">
                            <x-icon name="cash" class="w-4 h-4" />
                            {{ __('purchases.actions.record_payment') }}
                        </a>
                    @endcan
                @endif
            </div>
        </div>

        {{-- Oversold heads-up — full-width banner above the details. Shown on a
             still-receivable (draft) PO when one of its lines is currently
             oversold: receiving nets the backorder off first, so available
             stock rises by less than the quantity received. --}}
        @if ($purchase->status === 'draft' && !empty($oversoldLines))
            <x-alert type="warning" class="mb-5">
                <div>{{ __('purchases.oversold.warning', ['count' => count($oversoldLines)]) }}</div>
                <div class="mt-1 text-sm fg-tertiary">
                    {{ collect($oversoldLines)->map(fn ($l) => $l['name'].' ('.(rtrim(rtrim(number_format($l['oversold_by'], 4, '.', ''), '0'), '.') ?: '0').')')->join(', ') }}
                </div>
            </x-alert>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            {{-- LEFT: Header + Items --}}
            <div class="space-y-5">
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('purchases.sections.header') }}</div>
                    </div></div>
                    <div class="card-body">
                        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                            <div><dt class="fg-tertiary">{{ __('purchases.fields.supplier') }}</dt><dd>{{ $purchase->supplier?->name ?: '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('purchases.fields.store') }}</dt><dd>{{ $purchase->store?->name ?: '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('purchases.fields.currency') }}</dt><dd class="mono">{{ $purchase->currency_code }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('purchases.fields.purchase_date') }}</dt><dd>{{ format_date($purchase->purchase_date) }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('purchases.fields.due_date') }}</dt><dd>{{ $purchase->due_date ? format_date($purchase->due_date) : '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('purchases.fields.supplier_invoice') }}</dt><dd class="mono">{{ $purchase->supplier_invoice_number ?: '—' }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('purchases.sections.items') }}</div>
                    </div></div>
                    <div class="card-body">
                        @if ($purchase->items->isEmpty())
                            <p class="fg-tertiary text-sm">{{ __('purchases.items_empty') }}</p>
                        @else
                            <div class="dt-scroll">
                            <table class="dt-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('purchases.columns.product') }}</th>
                                        <th class="num">{{ __('purchases.columns.qty') }}</th>
                                        <th class="num">{{ __('purchases.columns.unit_cost') }}</th>
                                        <th class="num">{{ __('purchases.columns.discount') }}</th>
                                        <th>{{ __('purchases.columns.tax_group') }}</th>
                                        <th class="num">{{ __('purchases.columns.line_total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchase->items as $i)
                                        @php
                                            // For variant products show the picked variant's label + SKU
                                            // alongside the parent's name, so the receiving clerk can
                                            // tell apart "Shirt · Red / L" from "Shirt · Blue / L"
                                            // without opening the product. `effectiveSku` falls back
                                            // to the parent SKU when the variant has none.
                                            $productName  = $i->product?->name;
                                            $variantLabel = $i->variant?->label;
                                            $effectiveSku = $i->variant?->sku ?: $i->product?->sku;
                                            $titleParts   = array_filter([$productName, $variantLabel, $effectiveSku]);
                                            $titleText    = implode(' · ', $titleParts);
                                        @endphp
                                        <tr>
                                            <td title="{{ $titleText }}">
                                                <div class="leading-tight">
                                                    <div>
                                                        {{ $productName }}
                                                        @if ($variantLabel)
                                                            <span class="fg-secondary">· {{ $variantLabel }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="mono fg-tertiary text-xs">{{ $effectiveSku ?: '—' }}</div>
                                                    <x-admin.purchase-batch-line
                                                        :number="$i->batch_number"
                                                        :mfg="$i->manufacture_date"
                                                        :expiry="$i->expiry_date" />
                                                </div>
                                            </td>
                                            <td class="num tnum">{{ rtrim(rtrim((string) $i->quantity, '0'), '.') }}</td>
                                            <td class="num tnum">{{ format_money($i->unit_cost, null, $purchase->currency_code) }}</td>
                                            <td class="num tnum">{{ $i->discount_percent > 0 ? rtrim(rtrim((string) $i->discount_percent, '0'), '.').'%' : '—' }}</td>
                                            <td>{{ $i->taxGroup?->name ?: '—' }}</td>
                                            <td class="num tnum">{{ format_money($i->line_total, null, $purchase->currency_code) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- RIGHT: Totals + Notes --}}
            <div class="space-y-5">
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('purchases.sections.totals') }}</div>
                    </div></div>
                    <div class="card-body">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div><dt class="fg-tertiary">{{ __('purchases.totals.subtotal') }}</dt><dd class="num tnum">{{ format_money($purchase->subtotal, null, $purchase->currency_code) }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('purchases.totals.discount') }}</dt><dd class="num tnum">{{ format_money($purchase->discount_total, null, $purchase->currency_code) }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('purchases.totals.tax') }}</dt><dd class="num tnum">{{ format_money($purchase->tax_total, null, $purchase->currency_code) }}</dd></div>
                            <div class="col-span-2 border-t border-subtle pt-3"><dt class="font-semibold">{{ __('purchases.totals.grand') }}</dt><dd class="num tnum text-base font-semibold">{{ format_money($purchase->grand_total, null, $purchase->currency_code) }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('purchases.totals.paid') }}</dt><dd class="num tnum">{{ format_money($purchase->paid_total, null, $purchase->currency_code) }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('purchases.totals.balance') }}</dt><dd class="num tnum">{{ format_money($purchase->balance_due, null, $purchase->currency_code) }}</dd></div>
                        </dl>
                    </div>
                </div>

                @php($attachment = $purchase->attachments->first())
                @if ($attachment)
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('purchases.sections.attachment') }}</div>
                        </div></div>
                        <div class="card-body">
                            <a href="{{ route('admin.purchases.attachment.download', [$purchase, $attachment]) }}"
                               class="flex items-center gap-3 rounded-lg border border-subtle p-3 hover:bg-hover transition-colors">
                                <span class="fg-tertiary shrink-0">
                                    <x-icon name="{{ $attachment->isImage() ? 'image' : 'receipt' }}" class="w-6 h-6" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium truncate">{{ $attachment->original_filename }}</span>
                                    <span class="block text-xs fg-tertiary">{{ $attachment->humanSize() }}</span>
                                </span>
                                <span class="fg-tertiary shrink-0"><x-icon name="download" class="w-4 h-4" /></span>
                            </a>
                        </div>
                    </div>
                @endif

                @if ($purchase->notes)
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('purchases.sections.notes') }}</div>
                        </div></div>
                        <div class="card-body">
                            <p class="text-sm whitespace-pre-wrap">{{ $purchase->notes }}</p>
                        </div>
                    </div>
                @endif

                @if ($purchase->returns->isNotEmpty())
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('purchases.returns.title') }}</div>
                        </div></div>
                        <div class="card-body p-0">
                            <div class="dt-scroll">
                            <table class="dt-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('purchases.returns.columns.number') }}</th>
                                        <th>{{ __('purchases.returns.columns.date') }}</th>
                                        <th class="num">{{ __('purchases.returns.columns.total') }}</th>
                                        <th>{{ __('purchases.returns.columns.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchase->returns as $ret)
                                        <tr class="cursor-pointer hover:bg-hover"
                                            onclick="window.location='{{ route('admin.purchase-returns.show', $ret) }}'">
                                            <td class="mono font-medium">{{ $ret->number }}</td>
                                            <td>{{ format_date($ret->return_date) }}</td>
                                            <td class="num tnum">{{ format_money($ret->grand_total) }}</td>
                                            <td>
                                                <span class="prod-badge {{ $ret->status === 'posted' ? 'prod-badge-positive' : 'prod-badge-muted' }}">
                                                    {{ __('purchases.returns.status.'.$ret->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
