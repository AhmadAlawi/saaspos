<x-admin-layout
    active="shifts"
    :title="'#'.$shift->id"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('shifts.title'), 'href' => route('admin.shifts.index')],
        ['label' => '#'.$shift->id],
    ]">

    @php
        $statusBadge = match ($shift->status) {
            'open'                  => 'info',
            'closed'                => 'positive',
            'closed_with_variance'  => 'warning',
            default                 => 'muted',
        };
    @endphp

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.shifts.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('shifts.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">
                        <span class="mono">#{{ $shift->id }}</span>
                        <span class="prod-head-badge prod-head-badge-{{ $statusBadge }}">{{ __('shifts.statuses.'.$shift->status) }}</span>
                    </h1>
                    <p class="page-sub">
                        {{ $shift->store?->name }} ·
                        {{ $shift->cashier?->name ?? '—' }} ·
                        {{ __('shifts.fields.opened') }}: {{ format_datetime($shift->opened_at) }}
                        @if ($shift->closed_at)
                            · {{ __('shifts.fields.closed') }}: {{ format_datetime($shift->closed_at) }}
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if ($shift->isOpen())
                    <a href="{{ route('admin.shifts.x-report', $shift) }}" target="_blank" class="pos-btn pos-btn-sm pos-btn-ghost">
                        <x-icon name="receipt" class="w-4 h-4" />
                        {{ __('shifts.actions.view_x_report') }}
                    </a>
                @endif
                {{-- X/Z-report through the print bridge (WebUSB → browser-print),
                     same generic printButton factory the sale receipt uses. The
                     payload endpoint auto-labels itself X (open) or Z (closed) —
                     see ZReportEscPosFormatter/z-report-thermal. --}}
                <button type="button"
                        x-data="printButton({
                            payloadUrl:     '{{ route('admin.shifts.z-report.payload', $shift) }}',
                            logUrl:         '{{ route('admin.print-logs.store') }}',
                            referenceType:  'Shift',
                            referenceId:    {{ $shift->id }},
                            referenceLabel: '#{{ $shift->id }}',
                        })"
                        @click="print()"
                        :disabled="printing"
                        class="pos-btn pos-btn-sm pos-btn-primary">
                    <svg x-show="printing" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    <template x-if="!printing"><x-icon name="receipt" class="w-4 h-4" /></template>
                    <span>{{ $shift->isClosed() ? __('shifts.actions.print_z_report') : __('shifts.actions.print_x_report') }}</span>
                </button>
                @can ('close', $shift)
                    @if ($shift->isOpen())
                        @php $isOwnShift = (int) $shift->user_id === (int) auth()->id(); @endphp
                        <a href="{{ route('admin.shifts.close.form', $shift) }}"
                           class="pos-btn pos-btn-sm {{ $isOwnShift ? 'pos-btn-primary' : 'pos-btn-danger' }}">
                            <x-icon name="{{ $isOwnShift ? 'check' : 'alert' }}" class="w-4 h-4" />
                            {{ $isOwnShift ? __('shifts.actions.close_shift') : __('shifts.actions.force_close') }}
                        </a>
                    @endif
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            {{-- LEFT: totals + cash-drawer panel + entries log --}}
            <div class="space-y-5">
            <div class="card">
                <div class="card-header"><div>
                    <div class="card-title">
                        {{ $shift->isOpen()
                            ? __('shifts.sections.x_report')
                            : __('shifts.sections.z_report') }}
                    </div>
                    <div class="card-sub">
                        {{ $shift->isOpen()
                            ? __('shifts.sections.x_report_sub')
                            : __('shifts.sections.z_report_sub') }}
                    </div>
                </div></div>
                <div class="card-body">
                    <x-shifts.totals-table :totals="$totals" />

                    @if ($shift->isClosed())
                        <div class="mt-6 pt-4 border-t border-default">
                            <table class="dt-table dt-table-compact">
                                <tbody>
                                    <tr>
                                        <td>{{ __('shifts.totals.counted_cash') }}</td>
                                        <td class="num tnum">{{ format_money($shift->closing_cash_counted) }}</td>
                                    </tr>
                                    <tr class="font-semibold">
                                        <td>{{ __('shifts.totals.variance') }}</td>
                                        @php $vCmp = bccomp((string) $shift->cash_variance, '0', 4); @endphp
                                        <td class="num tnum">
                                            <span @class([
                                                'mono',
                                                'text-amber-600 dark:text-amber-400' => $vCmp !== 0,
                                            ])>{{ format_money($shift->cash_variance) }}</span>
                                        </td>
                                    </tr>
                                    @if ($shift->variance_reason)
                                        @php
                                            $reasonName = \App\Models\ShiftVarianceReason::query()
                                                ->where('code', $shift->variance_reason)
                                                ->value('name') ?? $shift->variance_reason;
                                        @endphp
                                        <tr>
                                            <td>{{ __('shifts.fields.variance_reason') }}</td>
                                            <td>{{ $reasonName }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            @if ($shift->isOpen())
                @include('admin.shifts._cash-drawer-panel', ['shift' => $shift])
            @endif

            @if ($entries->isNotEmpty())
                <div class="card card-pad-0">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('cash_drawer.entries.title') }}</div>
                        <div class="card-title-sub">{{ __('cash_drawer.entries.sub') }}</div>
                    </div></div>
                    <table class="dt-table">
                        <thead>
                            <tr>
                                <th>{{ __('cash_drawer.columns.when') }}</th>
                                <th>{{ __('cash_drawer.columns.type') }}</th>
                                <th>{{ __('cash_drawer.columns.reason') }}</th>
                                <th>{{ __('cash_drawer.columns.by') }}</th>
                                <th class="num">{{ __('cash_drawer.columns.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr>
                                    <td>{{ format_datetime($entry->created_at) }}</td>
                                    <td>
                                        @php
                                            $badge = match ($entry->type) {
                                                'pay_in'              => 'positive',
                                                'pay_out'             => 'warning',
                                                'drawer_open_no_sale' => 'info',
                                                default               => 'muted',
                                            };
                                        @endphp
                                        <span class="prod-badge prod-badge-{{ $badge }}">
                                            {{ __('cash_drawer.types.'.$entry->type) }}
                                        </span>
                                    </td>
                                    <td>{{ $entry->reason }}</td>
                                    <td>{{ $entry->createdBy?->name ?: '—' }}</td>
                                    <td class="num tnum">
                                        @if ($entry->type === 'drawer_open_no_sale')
                                            —
                                        @elseif ($entry->type === 'pay_out')
                                            <span class="text-rose-600 dark:text-rose-400">−{{ format_money($entry->amount) }}</span>
                                        @else
                                            <span class="text-emerald-600 dark:text-emerald-400">{{ format_money($entry->amount) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            </div>

            {{-- RIGHT: meta + notes --}}
            <div class="card">
                <div class="card-header"><div>
                    <div class="card-title">{{ __('shifts.sections.details') }}</div>
                </div></div>
                <div class="card-body space-y-3 text-sm">
                    <div>
                        <div class="text-muted">{{ __('shifts.fields.shift_id') }}</div>
                        <div class="mono">#{{ $shift->id }}</div>
                    </div>
                    <div>
                        <div class="text-muted">{{ __('shifts.fields.cashier') }}</div>
                        <div>{{ $shift->cashier?->name ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-muted">{{ __('shifts.fields.store') }}</div>
                        <div>{{ $shift->store?->name }}</div>
                    </div>
                    <div>
                        <div class="text-muted">{{ __('shifts.fields.opened') }}</div>
                        <div>{{ format_datetime($shift->opened_at) }}</div>
                    </div>
                    @if ($shift->closed_at)
                        <div>
                            <div class="text-muted">{{ __('shifts.fields.closed') }}</div>
                            <div>{{ format_datetime($shift->closed_at) }}</div>
                        </div>
                    @endif
                    @if ($shift->force_closed_by)
                        <div>
                            <div class="text-muted">{{ __('shifts.fields.force_closed_by') }}</div>
                            <div>{{ $shift->forceCloser?->name ?: '—' }}</div>
                        </div>
                    @endif
                    @if ($shift->notes)
                        <div>
                            <div class="text-muted">{{ __('shifts.fields.notes') }}</div>
                            <div class="whitespace-pre-line">{{ $shift->notes }}</div>
                        </div>
                    @endif
                    @if ($shift->variance_notes)
                        <div>
                            <div class="text-muted">{{ __('shifts.fields.variance_notes') }}</div>
                            <div class="whitespace-pre-line">{{ $shift->variance_notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
