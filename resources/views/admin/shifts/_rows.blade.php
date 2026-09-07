{{--
    Table-view rows for the Shifts list.

    Rendered inline by `admin.shifts.index` (first page) and standalone by
    `ShiftController@rows` (JSON `html`) for every subsequent page / search /
    sort. Read-only rows — the whole row links to the shift detail.

    Expects: $shifts (iterable of Shift, with store + cashier loaded).
--}}
@foreach ($shifts as $shift)
    <tr class="prod-row" data-dt-row
        data-dt-name="#{{ $shift->id }} {{ $shift->cashier?->name }}"
        data-dt-id="{{ $shift->id }}"
        data-dt-status="{{ $shift->status }}"
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.shifts.show', $shift)) }}">
        <td class="mono">#{{ $shift->id }}</td>
        <td>{{ $shift->cashier?->name ?: '—' }}</td>
        <td>{{ format_datetime($shift->opened_at) }}</td>
        <td>{{ $shift->closed_at ? format_datetime($shift->closed_at) : '—' }}</td>
        <td class="num tnum">{{ $shift->isClosed() ? format_money($shift->sales_total) : '—' }}</td>
        <td class="num tnum">
            @if ($shift->cash_variance !== null && $shift->isClosed())
                @php $vCmp = bccomp((string) $shift->cash_variance, '0', 4); @endphp
                <span @class([
                    'mono',
                    'text-amber-600 dark:text-amber-400' => $vCmp !== 0,
                ])>{{ format_money($shift->cash_variance) }}</span>
            @else
                —
            @endif
        </td>
        <td>
            @php
                $badge = match ($shift->status) {
                    'open'                  => 'info',
                    'closed'                => 'positive',
                    'closed_with_variance'  => 'warning',
                    default                 => 'muted',
                };
            @endphp
            <span class="prod-badge prod-badge-{{ $badge }}">
                {{ __('shifts.statuses.'.$shift->status) }}
            </span>
            {{-- Stale flag: an open shift older than ~a working day is likely
                 abandoned (cashier logged out without closing). --}}
            @if ($shift->isOpen())
                @php $hoursOpen = (int) $shift->opened_at->diffInHours(now()); @endphp
                @if ($hoursOpen >= 8)
                    <span class="prod-badge prod-badge-warning ms-1" title="{{ __('shifts.stale.tooltip') }}">
                        {{ __('shifts.stale.label', ['hours' => $hoursOpen]) }}
                    </span>
                @endif
            @endif
        </td>
        <td class="text-end" @click.stop>
            {{-- Same dual-format print bridge (WebUSB -> browser-print) the
                 sale receipt uses. An open shift's payload is the live
                 X-report; once closed, the same endpoint returns the frozen
                 Z-report — see ZReportEscPosFormatter/z-report-thermal. --}}
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
                    class="pos-btn pos-btn-sm pos-btn-ghost">
                <svg x-show="printing" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
                <template x-if="!printing"><x-icon name="receipt" class="w-4 h-4" /></template>
                <span>{{ $shift->isClosed() ? __('shifts.actions.print_z_report') : __('shifts.actions.print_x_report') }}</span>
            </button>
        </td>
    </tr>
@endforeach
