<x-admin-layout
    active="shifts"
    :title="__('shifts.day.close.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('shifts.title'), 'href' => route('admin.shifts.index')],
        ['label' => __('shifts.day.close.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.shifts.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('shifts.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('shifts.day.close.title') }}</h1>
                    <p class="page-sub">
                        {{ $tradingDay->store?->name }}
                        @if ($tradingDay->terminal)
                            · {{ $tradingDay->terminal->name }}
                        @else
                            · {{ __('shifts.day.no_terminal') }}
                        @endif
                        · {{ $tradingDay->business_date->toDateString() }} ·
                        {{ __('shifts.day_fields.shift_count') }}: {{ $totals['shift_count'] }}
                    </p>
                </div>
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-header"><div>
                <div class="card-title">{{ __('shifts.sections.sales_summary') }}</div>
            </div></div>
            <div class="card-body">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <div class="text-xs text-muted uppercase">{{ __('shifts.totals.sales_total') }}</div>
                        <div class="text-lg font-semibold mono tnum">{{ format_money($totals['sales_total']) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted uppercase">{{ __('shifts.totals.sales_count') }}</div>
                        <div class="text-lg font-semibold mono tnum">{{ $totals['sales_count'] }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted uppercase">{{ __('shifts.totals.refunds_total') }}</div>
                        <div class="text-lg font-semibold mono tnum">{{ format_money($totals['refunds_total']) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted uppercase">{{ __('shifts.totals.variance') }}</div>
                        <div class="text-lg font-semibold mono tnum">{{ format_money($totals['cash_variance_total']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-header"><div>
                <div class="card-title">{{ __('shifts.day_fields.by_employee') }}</div>
                <div class="card-sub">{{ __('shifts.day.close.employee_sub') }}</div>
            </div></div>
            <div class="card-body">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-muted text-xs uppercase">
                            <th class="pb-2">{{ __('shifts.fields.cashier') }}</th>
                            <th class="pb-2">{{ __('shifts.fields.opened') }}</th>
                            <th class="pb-2">{{ __('shifts.fields.closed') }}</th>
                            <th class="pb-2 text-end">{{ __('shifts.totals.sales_total') }}</th>
                            <th class="pb-2 text-end">{{ __('shifts.totals.refunds_total') }}</th>
                            <th class="pb-2 text-end">{{ __('shifts.totals.variance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tradingDay->shifts as $shift)
                            <tr class="border-t border-subtle">
                                <td class="py-2">{{ $shift->cashier?->name }}</td>
                                <td class="py-2">{{ format_datetime($shift->opened_at) }}</td>
                                <td class="py-2">{{ $shift->closed_at ? format_datetime($shift->closed_at) : '—' }}</td>
                                <td class="py-2 text-end mono tnum">{{ format_money($shift->sales_total) }}</td>
                                <td class="py-2 text-end mono tnum">{{ format_money($shift->refunds_total) }}</td>
                                <td class="py-2 text-end mono tnum">{{ format_money($shift->cash_variance ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.shifts.day.close', $tradingDay) }}" data-ajax-form>
            @csrf
            <div class="flex items-center justify-end gap-2">
                <a href="{{ route('admin.shifts.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost">{{ __('shifts.actions.cancel') }}</a>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ __('shifts.day.close.submit') }}
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>
