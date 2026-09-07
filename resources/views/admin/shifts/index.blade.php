<x-admin-layout
    active="shifts"
    :title="__('shifts.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('sales.title')],
        ['label' => __('shifts.title')],
    ]">

    {{-- Server-paginated. Only the first page renders inline; search, sort and
         paging fetch one page from `admin.shifts.rows`. --}}
    <div class="page-wide" x-data="shiftsIndexPage({{ \Illuminate\Support\Js::from([
        'endpoint'   => route('admin.shifts.rows'),
        'perPage'    => $perPage,
        'total'      => $total,
        'page'       => 1,
        'totalPages' => $totalPages,
    ]) }})">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('shifts.title') }}</h1>
                <p class="page-sub">{{ __('shifts.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                @if ($activeShift)
                    <a href="{{ route('admin.shifts.show', $activeShift) }}" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="receipt" class="w-4 h-4" />
                        {{ __('shifts.actions.view_active') }}
                    </a>
                @else
                    @can('create', App\Models\Shift::class)
                        <a href="{{ route('admin.shifts.open.form') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                            <x-icon name="plus" class="w-4 h-4" />
                            {{ __('shifts.actions.open') }}
                        </a>
                    @endcan
                @endif
            </div>
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        {{-- One row per open trading day (one per terminal) — the only way
             to see and close a terminal's day from off that terminal. Only
             shown to managers who can actually close one. --}}
        @if ($canCloseDay && $openTradingDays->isNotEmpty())
        <div class="card mb-5">
            <div class="card-header"><div>
                <div class="card-title">{{ __('shifts.day.open_days_title') }}</div>
                <div class="card-sub">{{ __('shifts.day.open_days_sub') }}</div>
            </div></div>
            <div class="card-body">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-muted text-xs uppercase">
                            <th class="pb-2">{{ __('shifts.fields.terminal') }}</th>
                            <th class="pb-2">{{ __('shifts.day_fields.opened_by') }}</th>
                            <th class="pb-2">{{ __('shifts.day_fields.opened') }}</th>
                            <th class="pb-2">{{ __('shifts.day_fields.shift_count') }}</th>
                            <th class="pb-2 text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($openTradingDays as $day)
                            <tr class="border-t border-subtle">
                                <td class="py-2">{{ $day->terminal?->name ?? __('shifts.day.no_terminal') }}</td>
                                <td class="py-2">{{ $day->openedBy?->name }}</td>
                                <td class="py-2">{{ format_datetime($day->opened_at) }}</td>
                                <td class="py-2">{{ $day->shifts_count }}</td>
                                <td class="py-2 text-end">
                                    {{-- Day-rollup report through the same print bridge as the
                                         per-shift X/Z buttons — WebUSB when the terminal's
                                         configured for it, browser-print otherwise (see
                                         PrepareDayReportPayload / DayReportEscPosFormatter). --}}
                                    <button type="button"
                                            x-data="printButton({
                                                payloadUrl:     '{{ route('admin.shifts.day.report-payload', $day) }}',
                                                logUrl:         '{{ route('admin.print-logs.store') }}',
                                                referenceType:  'TradingDay',
                                                referenceId:    {{ $day->id }},
                                                referenceLabel: @js($day->terminal?->name ?? __('shifts.day.no_terminal')),
                                            })"
                                            @click="print()"
                                            :disabled="printing"
                                            class="pos-btn pos-btn-sm pos-btn-ghost">
                                        <svg x-show="printing" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                        </svg>
                                        <template x-if="!printing"><x-icon name="receipt" class="w-4 h-4" /></template>
                                        <span>{{ __('shifts.day.print.title') }}</span>
                                    </button>
                                    <a href="{{ route('admin.shifts.day.close.form', $day) }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                                        {{ __('shifts.day.close.title') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- "No shifts at all" — distinct from "no rows match the search", which
             the data-table's own `.dt-empty` handles. --}}
        @if ($total === 0)
            <div class="card card-pad-0">
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="receipt" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('shifts.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('shifts.empty.sub') }}</div>
                </div>
            </div>
        @else
            <div class="card card-pad-0">
                <div class="dt-toolbar">
                    <div class="dt-toolbar-title">
                        {{ __('shifts.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                    </div>
                    {{-- Own search input (no `.debounce` — the server mixin
                         debounces the fetch itself, so the shared dt-search would
                         stack two delays). --}}
                    <div class="dt-search">
                        <span class="dt-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search"
                               x-model="search"
                               placeholder="{{ __('table.search_placeholder') }}"
                               aria-label="{{ __('table.search_placeholder') }}">
                        <button type="button" class="dt-search-clear" x-show="isFiltered" x-cloak
                                @click="clearSearch()" aria-label="{{ __('table.search_clear') }}">
                            <x-icon name="x" class="w-3.5 h-3.5" />
                        </button>
                    </div>
                    <x-admin.dt-toolbar-actions :show-sort="false" />
                </div>

                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('shifts.columns.number') }}</th>
                            <th>{{ __('shifts.columns.cashier') }}</th>
                            <th>{{ __('shifts.columns.opened') }}</th>
                            <th>{{ __('shifts.columns.closed') }}</th>
                            <th class="num">{{ __('shifts.columns.sales_total') }}</th>
                            <th class="num">{{ __('shifts.columns.variance') }}</th>
                            <th>{{ __('shifts.columns.status') }}</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.shifts._rows', ['shifts' => $shifts])
                    </tbody>
                </table>
                </div>

                {{-- No search results. --}}
                <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
                    <span class="dt-empty-icon"><x-icon name="search" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('table.empty.no_results_title') }}</div>
                    <div class="dt-empty-sub">{{ __('table.empty.no_results_sub') }}</div>
                </div>

                <x-admin.dt-pager />
            </div>
        @endif
    </div>
</x-admin-layout>
