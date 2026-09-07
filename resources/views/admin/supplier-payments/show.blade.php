<x-admin-layout
    active="supplier-payments"
    :title="$payment->reference ?: ('#'.$payment->id)"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('supplier_payments.crumb_parent')],
        ['label' => __('supplier_payments.title'), 'href' => route('admin.supplier-payments.index')],
        ['label' => $payment->reference ?: ('#'.$payment->id)],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.supplier-payments.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('supplier_payments.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">
                        {{ format_money($payment->amount) }}
                        @if ($payment->isVoided())
                            <span class="prod-head-badge prod-head-badge-muted">{{ __('supplier_payments.badges.voided') }}</span>
                        @endif
                    </h1>
                    <p class="page-sub">
                        {{ $payment->supplier?->name }} ·
                        {{ format_date($payment->payment_date) }} ·
                        {{ $payment->paymentMethod?->name }}
                    </p>
                </div>
            </div>

            {{-- Void action — gated by policy. Hidden once already voided
                 (double-void is rejected by the action anyway). Reason is
                 optional; a textarea-equipped dialog can land later if
                 ops asks for one. --}}
            @can('void', $payment)
                @if (! $payment->isVoided())
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-danger"
                            @click="$store.confirm.show({
                                title:        {{ \Illuminate\Support\Js::from(__('supplier_payments.void.confirm_title')) }},
                                message:      {{ \Illuminate\Support\Js::from(__('supplier_payments.void.confirm_body')) }},
                                intent:       'danger',
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('supplier_payments.void.action')) }},
                                cancelLabel:  {{ \Illuminate\Support\Js::from(__('supplier_payments.actions.discard')) }},
                                onConfirm:    () => $submitForm({{ \Illuminate\Support\Js::from(route('admin.supplier-payments.void', $payment)) }}),
                            })">
                        <x-icon name="x" class="w-4 h-4" />
                        {{ __('supplier_payments.void.action') }}
                    </button>
                @endif
            @endcan
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
            <div class="space-y-5">
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('supplier_payments.sections.header') }}</div>
                    </div></div>
                    <div class="card-body">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div><dt class="fg-tertiary">{{ __('supplier_payments.fields.supplier') }}</dt><dd>{{ $payment->supplier?->name }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('supplier_payments.fields.store') }}</dt><dd>{{ $payment->store?->name }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('supplier_payments.fields.date') }}</dt><dd>{{ format_date($payment->payment_date) }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('supplier_payments.fields.method') }}</dt><dd>{{ $payment->paymentMethod?->name }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('supplier_payments.fields.reference') }}</dt><dd class="mono">{{ $payment->reference ?: '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('supplier_payments.fields.purchase') }}</dt><dd class="mono">
                                @if ($payment->purchase)
                                    <a href="{{ route('admin.purchases.show', $payment->purchase) }}">{{ $payment->purchase->number }}</a>
                                @else
                                    —
                                @endif
                            </dd></div>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                @if ($payment->isVoided())
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('supplier_payments.void.title') }}</div>
                        </div></div>
                        <div class="card-body">
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                                <div><dt class="fg-tertiary">{{ __('supplier_payments.void.voided_at') }}</dt><dd>{{ format_datetime($payment->voided_at) }}</dd></div>
                                <div><dt class="fg-tertiary">{{ __('supplier_payments.void.voided_by') }}</dt><dd>{{ $payment->voider?->name ?? '—' }}</dd></div>
                                @if ($payment->void_reason)
                                    <div class="col-span-2"><dt class="fg-tertiary">{{ __('supplier_payments.void.reason') }}</dt><dd>{{ $payment->void_reason }}</dd></div>
                                @endif
                            </dl>
                        </div>
                    </div>
                @endif

                @if ($payment->notes)
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('supplier_payments.sections.notes') }}</div>
                        </div></div>
                        <div class="card-body">
                            <p class="text-sm whitespace-pre-wrap">{{ $payment->notes }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
