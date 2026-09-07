<x-admin-layout
    active="shifts"
    :title="__('shifts.sections.x_report')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('shifts.title'), 'href' => route('admin.shifts.index')],
        ['label' => '#'.$shift->id, 'href' => route('admin.shifts.show', $shift)],
        ['label' => __('shifts.actions.view_x_report')],
    ]">

    <div class="xreport-sheet">
        <div class="xreport-head">
            <div class="xreport-title">{{ __('shifts.sections.x_report') }}</div>
            <div class="xreport-meta">
                {{ __('shifts.fields.shift_id_value', ['n' => '#'.$shift->id]) }}
                · {{ $shift->store?->name }}
                · {{ $shift->cashier?->name }}
            </div>
            <div class="xreport-meta">
                {{ __('shifts.fields.opened') }}: {{ format_datetime($shift->opened_at) }}
            </div>
        </div>

        <x-shifts.totals-table :totals="$totals" />

        <div class="xreport-actions">
            <button type="button" class="pos-btn pos-btn-sm pos-btn-primary" @click="window.print()">
                <x-icon name="receipt" class="w-4 h-4" />
                {{ __('shifts.actions.print') }}
            </button>
            <a href="{{ route('admin.shifts.show', $shift) }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                {{ __('shifts.actions.cancel') }}
            </a>
        </div>
    </div>
</x-admin-layout>
