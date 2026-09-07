<x-admin-layout
    active="suppliers"
    :title="$supplier->name"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('suppliers.crumb_parent')],
        ['label' => __('suppliers.title'), 'href' => route('admin.suppliers.index')],
        ['label' => $supplier->name],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.suppliers.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('suppliers.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">
                        {{ $supplier->name }}
                        @if (! $supplier->is_active)
                            <span class="prod-badge prod-badge-muted ms-2">{{ __('suppliers.badges.inactive') }}</span>
                        @endif
                    </h1>
                    <p class="page-sub">
                        <span class="mono">{{ $supplier->code ?: '—' }}</span>
                        @if ($supplier->phone) · <span class="mono">{{ \App\Support\PhoneFormatter::pretty($supplier->phone) }}</span> @endif
                        @if ($supplier->email) · {{ $supplier->display_email }} @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2" x-data>
                @can('delete', $supplier)
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger"
                            @click="$store.confirm.show({
                                title: {{ \Illuminate\Support\Js::from(__('suppliers.confirm_delete.title', ['name' => $supplier->name])) }},
                                message: {{ \Illuminate\Support\Js::from(__('suppliers.confirm_delete.message')) }},
                                intent: 'danger',
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('suppliers.confirm_delete.confirm')) }},
                                onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.suppliers.destroy', $supplier)) }}),
                            })">
                        <x-icon name="trash" class="w-4 h-4" />
                        {{ __('suppliers.actions.delete') }}
                    </button>
                @endcan
                @can('update', $supplier)
                    <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="edit" class="w-4 h-4" />
                        {{ __('suppliers.actions.edit') }}
                    </a>
                @endcan
            </div>
        </div>

        {{-- KPI cards. Lifetime spend / last order = zero until Purchases
             slice lands. Outstanding is maintained server-side once
             supplier payments + purchase receipts wire it. --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-5">
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('suppliers.kpis.lifetime_spend') }}</div>
                    <div class="cust-kpi-value">{{ format_money(0) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('suppliers.kpis.outstanding') }}</div>
                    <div class="cust-kpi-value">{{ format_money($supplier->outstanding_balance) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('suppliers.kpis.last_order') }}</div>
                    <div class="cust-kpi-value">—</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
            {{-- LEFT: Profile + Tax --}}
            <div class="space-y-5">
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('suppliers.sections.profile') }}</div>
                    </div></div>
                    <div class="card-body">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div><dt class="fg-tertiary">{{ __('suppliers.fields.business_name') }}</dt><dd>{{ $supplier->business_name ?: '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('suppliers.fields.contact_person') }}</dt><dd>{{ $supplier->contact_person ?: '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('suppliers.fields.phone') }}</dt><dd class="mono">{{ $supplier->phone ? \App\Support\PhoneFormatter::pretty($supplier->phone) : '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('suppliers.fields.email') }}</dt><dd>{{ $supplier->email ? $supplier->display_email : '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('suppliers.fields.default_currency') }}</dt><dd class="mono">{{ $supplier->default_currency_code ?: '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('suppliers.fields.payment_terms_days') }}</dt><dd>{{ $supplier->payment_terms_days !== null ? $supplier->payment_terms_days.' '.__('suppliers.fields.days') : '—' }}</dd></div>
                        </dl>
                    </div>
                </div>

                @if ($supplier->gstin || $supplier->pan || $supplier->tax_registration_number)
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('suppliers.sections.tax') }}</div>
                        </div></div>
                        <div class="card-body">
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                                <div><dt class="fg-tertiary">{{ __('suppliers.fields.gstin') }}</dt><dd class="mono">{{ $supplier->gstin ?: '—' }}</dd></div>
                                <div><dt class="fg-tertiary">{{ __('suppliers.fields.pan') }}</dt><dd class="mono">{{ $supplier->pan ?: '—' }}</dd></div>
                                <div class="col-span-2"><dt class="fg-tertiary">{{ __('suppliers.fields.tax_reg') }}</dt><dd class="mono">{{ $supplier->tax_registration_number ?: '—' }}</dd></div>
                            </dl>
                        </div>
                    </div>
                @endif
            </div>

            {{-- RIGHT: Address + Notes --}}
            <div class="space-y-5">
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('suppliers.sections.address') }}</div>
                    </div></div>
                    <div class="card-body">
                        @php
                            $addr = collect([
                                $supplier->address_line1, $supplier->address_line2,
                                $supplier->city, $supplier->state, $supplier->postal_code, $supplier->country_code,
                            ])->filter()->implode(', ');
                        @endphp
                        <p class="text-sm {{ $addr === '' ? 'fg-tertiary' : '' }}">{{ $addr ?: '—' }}</p>
                    </div>
                </div>

                @if ($supplier->notes)
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('suppliers.sections.notes') }}</div>
                        </div></div>
                        <div class="card-body">
                            <p class="text-sm whitespace-pre-wrap">{{ $supplier->notes }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Placeholder for the upcoming tabs (Purchases / Payments /
             Statement / Returns / Activity) — built out in following
             slices once the relevant tables have rows to render. --}}
    </div>
</x-admin-layout>
