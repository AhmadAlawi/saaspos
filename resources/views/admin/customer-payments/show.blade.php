<x-admin-layout
    active="customer-payments"
    :title="__('customer_payments.show.title', ['id' => $payment->id])"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('customer_payments.crumb_parent')],
        ['label' => __('customer_payments.title'), 'href' => route('admin.customer-payments.index')],
        ['label' => '#'.$payment->id],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.customer-payments.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('customer_payments.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">
                        <span class="mono">#{{ $payment->id }}</span>
                        @if (! $payment->sale_id)
                            <span class="prod-head-badge prod-head-badge-positive">{{ __('customer_payments.unallocated_credit') }}</span>
                        @endif
                    </h1>
                    <p class="page-sub">
                        {{ $payment->customer?->name }} ·
                        {{ format_date($payment->paid_at) }} ·
                        {{ $payment->paymentMethod?->name }}
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            <div class="space-y-5">
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('customer_payments.show.details') }}</div>
                    </div></div>
                    <div class="card-body">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div><dt class="fg-tertiary">{{ __('customer_payments.fields.customer') }}</dt><dd>{{ $payment->customer?->name ?: '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('customer_payments.fields.date') }}</dt><dd>{{ format_date($payment->paid_at) }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('customer_payments.fields.method') }}</dt><dd>{{ $payment->paymentMethod?->name ?: '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('customer_payments.fields.reference') }}</dt><dd class="mono">{{ $payment->reference ?: '—' }}</dd></div>
                            <div>
                                <dt class="fg-tertiary">{{ __('customer_payments.columns.sale') }}</dt>
                                <dd>
                                    @if ($payment->sale)
                                        <a href="{{ route('admin.sales.show', $payment->sale) }}" class="link mono">{{ $payment->sale->number }}</a>
                                    @else
                                        <span class="fg-tertiary">{{ __('customer_payments.unallocated_credit') }}</span>
                                    @endif
                                </dd>
                            </div>
                            <div><dt class="fg-tertiary">{{ __('customer_payments.fields.recorded_by') }}</dt><dd>{{ $payment->createdBy?->name ?: '—' }}</dd></div>
                        </dl>

                        @if ($payment->notes)
                            <div class="mt-4 pt-4 border-t">
                                <div class="fg-tertiary text-xs mb-1">{{ __('customer_payments.fields.notes') }}</div>
                                <p class="whitespace-pre-line">{{ $payment->notes }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($siblings->isNotEmpty())
                    <div class="card card-pad-0">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('customer_payments.show.sibling_allocations') }}</div>
                            <div class="card-title-sub">{{ __('customer_payments.show.sibling_allocations_sub') }}</div>
                        </div></div>
                        <table class="dt-table">
                            <thead>
                                <tr>
                                    <th>{{ __('customer_payments.columns.sale') }}</th>
                                    <th class="num">{{ __('customer_payments.columns.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($siblings as $s)
                                    <tr>
                                        <td class="mono">
                                            @if ($s->sale)
                                                <a href="{{ route('admin.sales.show', $s->sale) }}" class="link">{{ $s->sale->number }}</a>
                                            @else
                                                <span class="fg-tertiary">{{ __('customer_payments.unallocated_credit') }}</span>
                                            @endif
                                        </td>
                                        <td class="num tnum">{{ format_money($s->amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="space-y-5">
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('customer_payments.totals.amount') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-sm fg-secondary">{{ __('customer_payments.columns.amount') }}</span>
                            <span class="num tnum text-2xl font-semibold leading-none">{{ format_money($payment->amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
