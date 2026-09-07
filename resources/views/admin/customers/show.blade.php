<x-admin-layout
    active="customers"
    :title="$customer->name"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('customers.crumb_parent')],
        ['label' => __('customers.title'), 'href' => route('admin.customers.index')],
        ['label' => $customer->name],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.customers.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('customers.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">
                        {{ $customer->name }}
                        @if ($customer->is_business)
                            <span class="prod-badge prod-badge-muted ms-2">{{ __('customers.badges.business') }}</span>
                        @endif
                        @if (! $customer->is_active)
                            <span class="prod-badge prod-badge-muted ms-2">{{ __('customers.badges.inactive') }}</span>
                        @endif
                    </h1>
                    <p class="page-sub">
                        <span class="mono">{{ $customer->code ?: '—' }}</span>
                        @if ($customer->phone) · <span class="mono">{{ \App\Support\PhoneFormatter::pretty($customer->phone) }}</span> @endif
                        @if ($customer->email) · {{ $customer->display_email }} @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2" x-data>
                {{-- Statement is the customer-facing receivables receipt;
                     always available, so the owner can hand a fresh
                     copy on any visit, not just when something's owed. --}}
                <a href="{{ route('admin.customers.statement.show', $customer) }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                    <x-icon name="receipt" class="w-4 h-4" />
                    {{ __('customers.actions.statement') }}
                </a>
                @if (bccomp((string) ($customer->outstanding_balance ?? '0'), '0', 4) > 0)
                    @can('create', App\Models\SalePayment::class)
                        <a href="{{ route('admin.customer-payments.create', ['customer_id' => $customer->id]) }}"
                           class="pos-btn pos-btn-sm pos-btn-primary">
                            <x-icon name="cash" class="w-4 h-4" />
                            {{ __('customers.actions.record_payment') }}
                        </a>
                    @endcan
                @endif
                @can('delete', $customer)
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger"
                            @click="$store.confirm.show({
                                title: {{ \Illuminate\Support\Js::from(__('customers.confirm_delete.title', ['name' => $customer->name])) }},
                                message: {{ \Illuminate\Support\Js::from(__('customers.confirm_delete.message')) }},
                                intent: 'danger',
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('customers.confirm_delete.confirm')) }},
                                onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.customers.destroy', $customer)) }}),
                            })">
                        <x-icon name="trash" class="w-4 h-4" />
                        {{ __('customers.actions.delete') }}
                    </button>
                @endcan
                @can('update', $customer)
                    <a href="{{ route('admin.customers.edit', $customer) }}" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="edit" class="w-4 h-4" />
                        {{ __('customers.actions.edit') }}
                    </a>
                @endcan
            </div>
        </div>

        {{-- KPI cards. Loyalty / outstanding / store credit are zero until Sales lands.
             gap-5 + full card padding to match the rest of the page (cards
             below this row use gap-5; KPI cards used to feel cramped). --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-5">
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('customers.kpis.outstanding') }}</div>
                    <div class="cust-kpi-value">{{ format_money($customer->outstanding_balance) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('customers.kpis.credit') }}</div>
                    <div class="cust-kpi-value">{{ format_money($customer->store_credit_balance) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('customers.kpis.loyalty') }}</div>
                    <div class="cust-kpi-value">{{ number_format($customer->loyalty_points) }}</div>
                </div>
            </div>
        </div>

        {{-- Two-column layout — cards stack inside per-column wrappers
             with `space-y-5` so each column flows naturally instead of
             a grid-row aligning Profile + Addresses and leaving an empty
             gap (see memory table-and-form-conventions). --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
            {{-- ── LEFT column: Profile + Addresses ─────────────── --}}
            <div class="space-y-5">
                {{-- Profile --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('customers.sections.profile') }}</div>
                    </div></div>
                    <div class="card-body">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div><dt class="fg-tertiary">{{ __('customers.fields.phone') }}</dt><dd class="mono">{{ $customer->phone ? \App\Support\PhoneFormatter::pretty($customer->phone) : '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('customers.fields.whatsapp_phone') }}</dt><dd class="mono">{{ $customer->whatsapp_phone ? \App\Support\PhoneFormatter::pretty($customer->whatsapp_phone) : '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('customers.fields.email') }}</dt><dd>{{ $customer->email ? $customer->display_email : '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('customers.fields.dob') }}</dt><dd>{{ $customer->dob ? format_date($customer->dob) : '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('customers.fields.gender') }}</dt><dd>{{ $customer->gender ? __('customers.gender.'.$customer->gender) : '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('customers.fields.group') }}</dt><dd>{{ $customer->group?->name ?: '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('customers.fields.discount') }}</dt><dd>{{ $customer->default_discount_percent ? rtrim(rtrim(number_format((float) $customer->default_discount_percent, 4, '.', ''), '0'), '.').'%' : '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('customers.fields.credit_limit') }}</dt><dd>{{ $customer->credit_limit ? format_money($customer->credit_limit) : '—' }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('customers.kpis.since') }}</dt><dd>{{ format_date($customer->created_at) }}</dd></div>
                            <div><dt class="fg-tertiary">{{ __('customers.kpis.first_store') }}</dt><dd>{{ $customer->firstStore?->name ?: '—' }}</dd></div>
                        </dl>
                    </div>
                </div>

                {{-- Addresses --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('customers.sections.addresses') }}</div>
                    </div></div>
                    <div class="card-body">
                        @if ($customer->addresses->isEmpty())
                            <p class="fg-tertiary">—</p>
                        @else
                            <div class="form-stack">
                                @foreach ($customer->addresses as $a)
                                    <div class="text-sm">
                                        <div class="flex items-center gap-2">
                                            <strong>{{ $a->label ?: __('customers.address.label') }}</strong>
                                            @if ($a->is_default)
                                                <span class="prod-badge prod-badge-positive">{{ __('customers.address.is_default') }}</span>
                                            @endif
                                        </div>
                                        <div class="fg-secondary">
                                            {{ collect([$a->line1, $a->line2, $a->landmark, $a->city, $a->state, $a->postal_code, $a->country_code])->filter()->implode(', ') ?: '—' }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ── RIGHT column: Business (if) + Open sales + Notes (if) ────── --}}
            <div class="space-y-5">
                {{-- Open sales drill-down — listed oldest-first, with
                     per-sale aging. Sale row clicks through to the
                     sale show page where the cashier can record a
                     payment or process a refund. --}}
                @if ($openSales->isNotEmpty())
                    @php $asOf = \Carbon\CarbonImmutable::today(); @endphp
                    <div class="card card-pad-0">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('customers.sections.open_sales') }}</div>
                            <div class="card-title-sub">{{ __('customers.sections.open_sales_sub', ['count' => $openSales->count()]) }}</div>
                        </div></div>
                        <table class="dt-table">
                            <thead>
                                <tr>
                                    <th>{{ __('customers.open_sales.sale') }}</th>
                                    <th>{{ __('customers.open_sales.date') }}</th>
                                    <th class="num">{{ __('customers.open_sales.age') }}</th>
                                    <th class="num">{{ __('customers.open_sales.balance') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($openSales as $sale)
                                    @php $age = (int) $asOf->diffInDays(\Carbon\CarbonImmutable::parse($sale->sale_date)->startOfDay()); @endphp
                                    <tr class="prod-row"
                                        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.sales.show', $sale)) }}">
                                        <td class="mono">{{ $sale->number }}</td>
                                        <td>{{ format_date($sale->sale_date) }}</td>
                                        <td class="num tnum
                                            @if ($age > 90) text-rose-700 dark:text-rose-500 font-semibold
                                            @elseif ($age > 60) text-rose-600 dark:text-rose-400
                                            @elseif ($age > 30) text-amber-600 dark:text-amber-400
                                            @else fg-tertiary
                                            @endif">
                                            {{ $age }}d
                                        </td>
                                        <td class="num tnum font-semibold">{{ format_money($sale->balance_due) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($customer->is_business)
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('customers.sections.business') }}</div>
                        </div></div>
                        <div class="card-body">
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                                <div class="col-span-2"><dt class="fg-tertiary">{{ __('customers.fields.business_name') }}</dt><dd>{{ $customer->business_name ?: '—' }}</dd></div>
                                <div><dt class="fg-tertiary">{{ __('customers.fields.gstin') }}</dt><dd class="mono">{{ $customer->gstin ?: '—' }}</dd></div>
                                <div><dt class="fg-tertiary">{{ __('customers.fields.pan') }}</dt><dd class="mono">{{ $customer->pan ?: '—' }}</dd></div>
                                <div class="col-span-2"><dt class="fg-tertiary">{{ __('customers.fields.tax_reg') }}</dt><dd class="mono">{{ $customer->tax_registration_number ?: '—' }}</dd></div>
                            </dl>
                        </div>
                    </div>
                @endif

                @if ($customer->notes)
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('customers.sections.notes') }}</div>
                        </div></div>
                        <div class="card-body">
                            <p class="text-sm whitespace-pre-wrap">{{ $customer->notes }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
