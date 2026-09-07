@php
    // Greeting + subtitle always reflect "now"; the KPI data reflects the
    // selected range (resolved in the controller → $rangeKey / $rangeLabel).
    $hour     = now()->hour;
    $greeting = match(true) {
        $hour < 12  => __('admin.dashboard.greeting_morning'),
        $hour < 17  => __('admin.dashboard.greeting_afternoon'),
        default     => __('admin.dashboard.greeting_evening'),
    };
    $userName   = auth()->user()?->name ?? __('admin.shell.guest');
    $dateLabel  = now()->format('l, M j');
    $timeLabel  = now()->format('H:i T');
    $dayName    = now()->format('l');

    // Header preset links: key => label. The custom range is its own form.
    $rangePresets = [
        'today'     => __('admin.dashboard.date_range_today'),
        'yesterday' => __('admin.dashboard.date_range_yesterday'),
        '7d'        => __('admin.dashboard.date_range_7d'),
        '15d'       => __('admin.dashboard.date_range_15d'),
        '30d'       => __('admin.dashboard.date_range_30d'),
        '60d'       => __('admin.dashboard.date_range_60d'),
        '90d'       => __('admin.dashboard.date_range_90d'),
    ];
@endphp

<x-admin-layout
    active="dashboard"
    :title="__('admin.dashboard.title')"
    :crumbs="[['label' => __('admin.dashboard.title')]]">

    {{-- Server data injected into window so Alpine's x-data attribute never contains raw JSON --}}
    <script>window.DASHBOARD_DATA = {!! json_encode($alpineConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) !!};</script>

    <div class="page-wide" x-data="dashboardPage">

        {{-- ══ Header ══════════════════════════════════════════════════ --}}
        <div class="page-header mb-6">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h1 class="page-title">{{ $greeting }}, {{ $userName }}</h1>
                    <span class="live-pulse">{{ __('admin.dashboard.live_label') }}</span>
                </div>
                <p class="page-sub">
                    {{ $dateLabel }} · {{ $timeLabel }}
                    @if($cashiersOnShift > 0)
                        <span class="mx-1.5 fg-tertiary">·</span>
                        <span class="positive font-medium">{{ $cashiersOnShift }} {{ __('admin.dashboard.ops.cashiers_on_shift') }}</span>
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                {{-- Date-range filter: preset links (?range=…) + a custom span
                     (?range=custom&from=&to=). The whole KPI block + hero chart
                     reflect the chosen range; lower sections keep their own
                     granular selectors. --}}
                <div class="relative" x-data="{ open: false }">
                    <button class="date-range" @click="open = !open" @keydown.escape.window="open = false">
                        <x-icon name="refresh" class="w-3.5 h-3.5 fg-tertiary" />
                        {{ $rangeLabel }}
                        <x-icon name="chevron" class="w-3.5 h-3.5 fg-tertiary" />
                    </button>
                    <div x-show="open" x-cloak
                         @click.outside="if (!$event.target.closest('.flatpickr-calendar')) open = false"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute start-0 sm:start-auto sm:end-0 top-full mt-1.5 z-50 min-w-[210px] max-w-[calc(100vw-24px)] rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] shadow-lg overflow-hidden py-1">
                        @foreach ($rangePresets as $key => $label)
                            <a href="{{ route('admin.dashboard', ['range' => $key]) }}"
                               class="flex items-center gap-2 px-3.5 py-2 text-[13px] hover:bg-[var(--bg-hover)] {{ $rangeKey === $key ? 'font-semibold accent' : 'fg-primary' }}">
                                {{ $label }}
                                @if($rangeKey === $key)<x-icon name="check" class="w-3.5 h-3.5 ms-auto" />@endif
                            </a>
                        @endforeach

                        <div class="my-1 border-t border-[var(--border-subtle)]"></div>

                        {{-- Custom range --}}
                        <form method="GET" action="{{ route('admin.dashboard') }}" class="px-3.5 py-2">
                            <input type="hidden" name="range" value="custom">
                            <div class="flex items-center gap-1 mb-1.5">
                                <x-icon name="clock" class="w-3.5 h-3.5 fg-tertiary" />
                                <span class="text-[12px] font-medium fg-secondary">{{ __('admin.dashboard.date_range_custom') }}</span>
                                @if($rangeKey === 'custom')<x-icon name="check" class="w-3.5 h-3.5 ms-auto accent" />@endif
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="block">
                                    <span class="block text-[11px] fg-tertiary mb-1">{{ __('admin.dashboard.date_range_from') }}</span>
                                    <input type="text" name="from" value="{{ $customFrom }}"
                                           class="pos-input pos-input-sm js-datepicker w-full"
                                           placeholder="YYYY-MM-DD" autocomplete="off">
                                </label>
                                <label class="block">
                                    <span class="block text-[11px] fg-tertiary mb-1">{{ __('admin.dashboard.date_range_to') }}</span>
                                    <input type="text" name="to" value="{{ $customTo }}"
                                           class="pos-input pos-input-sm js-datepicker w-full"
                                           placeholder="YYYY-MM-DD" autocomplete="off">
                                </label>
                            </div>
                            <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary w-full mt-2">
                                {{ __('admin.dashboard.date_range_apply') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

      

        {{-- ══ Setup checklist (new installs only) ════════════════════ --}}
        {{-- Hidden once every essential step is done: the visit that completes
             the last one shows a one-time celebration, then the card is gone.
             `$setup` is null whenever there's nothing to show. --}}
        @if ($setup)
            <div class="setup-card mb-6"
                 x-data="setupChecklist({{ Js::from(route('admin.dashboard.dismiss-setup')) }})"
                 x-show="visible" x-cloak>
                @if ($setup['celebrate'] ?? false)
                    {{-- One-time congratulations — every essential is in place. --}}
                    <div class="setup-card-head">
                        <div class="setup-celebrate-head">
                            <span class="setup-celebrate-icon"><x-icon name="check" class="w-5 h-5" /></span>
                            <div>
                                <div class="setup-card-title">{{ __('admin.dashboard.setup.celebrate_title') }}</div>
                                <div class="setup-card-sub">{{ __('admin.dashboard.setup.celebrate_sub') }}</div>
                            </div>
                        </div>
                        <button type="button" class="setup-dismiss" @click="dismiss()">
                            {{ __('admin.dashboard.setup.dismiss') }}
                        </button>
                    </div>
                    <div class="setup-progress-row">
                        <div class="setup-progress-track">
                            <div class="setup-progress-fill" style="width:100%"></div>
                        </div>
                        <div class="setup-progress-label">100%</div>
                    </div>
                    <a href="{{ route('cashier.index') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                        {{ __('admin.dashboard.setup.celebrate_cta') }}
                    </a>
                @else
                    <div class="setup-card-head">
                        <div>
                            <div class="setup-card-title">{{ __('admin.dashboard.setup.title') }}</div>
                            <div class="setup-card-sub">{{ __('admin.dashboard.setup.sub') }}</div>
                        </div>
                        <button type="button" class="setup-dismiss" @click="dismiss()">
                            {{ __('admin.dashboard.setup.dismiss') }}
                        </button>
                    </div>

                    {{-- Progress reflects the ESSENTIAL steps only, so 100% means
                         "ready to sell" rather than "answered every prompt". --}}
                    <div class="setup-progress-row">
                        <div class="setup-progress-track"
                             role="progressbar"
                             aria-valuemin="0" aria-valuemax="100"
                             aria-valuenow="{{ $setup['percent'] }}"
                             aria-label="{{ __('admin.dashboard.setup.title') }}">
                            <div class="setup-progress-fill" style="width:{{ $setup['percent'] }}%"></div>
                        </div>
                        <div class="setup-progress-label">
                            {{ $setup['percent'] }}%
                            <span class="setup-progress-count">
                                {{ __('admin.dashboard.setup.progress', ['done' => $setup['done'], 'total' => $setup['total']]) }}
                            </span>
                        </div>
                    </div>

                    <div class="setup-steps">
                        @foreach ($setup['steps'] as $step)
                            @include('admin.partials.setup-step', ['step' => $step])
                        @endforeach
                    </div>

                    @if (! empty($setup['optional']))
                        <div class="setup-optional-head">
                            <span class="setup-optional-title">{{ __('admin.dashboard.setup.optional_title') }}</span>
                            <span class="setup-optional-sub">{{ __('admin.dashboard.setup.optional_sub') }}</span>
                        </div>
                        <div class="setup-steps setup-steps-optional">
                            @foreach ($setup['optional'] as $step)
                                @include('admin.partials.setup-step', ['step' => $step])
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        @endif

        {{-- ══ ROW 1 — Hero stat + Mini KPIs ══════════════════════════ --}}
        <div class="grid grid-cols-12 gap-3">

            {{-- Hero card --}}
            <div class="stat-hero col-span-12 lg:col-span-7">
                <div class="flex items-center justify-between">
                    <div class="eyebrow">{{ $isToday ? __('admin.dashboard.kpi.today_sales') : $periodLabel }}</div>
                    <div class="flex items-center gap-2">
                        <span class="trend-chip trend-up">
                            <x-icon name="trending" class="w-3 h-3" />
                            +0.0%
                        </span>
                        <span class="text-[11.5px] fg-tertiary">{{ __('admin.dashboard.kpi.vs_avg', ['day' => $dayName]) }}</span>
                    </div>
                </div>
                <div class="number tnum">{{ format_money($todaySales) }}</div>
                <div class="meta-row">
                    <span class="now-pill">
                        <x-icon name="cart" class="w-3 h-3" />
                        <span class="tnum">
                            <span class="font-semibold fg-primary">{{ number_format($todayTxns) }}</span>
                            {{ __('admin.dashboard.kpi.transactions') }}
                        </span>
                    </span>
                    <span class="now-pill">
                        <span class="tnum">
                            <span class="font-semibold fg-primary">{{ format_money($avgBasket) }}</span>
                            {{ __('admin.dashboard.kpi.avg_basket') }}
                        </span>
                    </span>
                    <span class="now-pill">
                        <span class="tnum">
                            <span class="font-semibold fg-primary">{{ number_format($todayItems, 0) }}</span>
                            {{ __('admin.dashboard.kpi.items') }}
                        </span>
                    </span>
                </div>
                <div class="chart-bg" x-html="heroAreaChart()"
                     @mousemove="onChartMove($event)" @mouseleave="onChartLeave()"></div>
            </div>

            {{-- Mini KPI stack --}}
            <div class="col-span-12 lg:col-span-5 grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-1 gap-3">
                <a href="{{ route('admin.sales.index') }}" class="mini-kpi">
                    <span class="mini-kpi-icon"><x-icon name="cart" class="w-4 h-4" /></span>
                    <div class="body">
                        <div class="flex items-center justify-between gap-2">
                            <div class="label">{{ __('admin.dashboard.kpi.transactions') }}</div>
                            <span class="trend-chip trend-up">+0%</span>
                        </div>
                        <div class="num tnum">{{ number_format($todayTxns) }}</div>
                    </div>
                    <div class="spark" x-html="miniSparkSmooth(txnByDay, 'var(--positive)')"></div>
                </a>
                <a href="{{ route('admin.customers.index') }}" class="mini-kpi">
                    <span class="mini-kpi-icon"><x-icon name="customers" class="w-4 h-4" /></span>
                    <div class="body">
                        <div class="flex items-center justify-between gap-2">
                            <div class="label">{{ __('admin.dashboard.kpi.new_customers') }}</div>
                            <span class="trend-chip trend-up">+0%</span>
                        </div>
                        <div class="num tnum">{{ number_format($newCustomers) }}</div>
                    </div>
                    <div class="spark" x-html="miniSparkSmooth(txnByDay.slice(-14), 'var(--positive)')"></div>
                </a>
                <a href="{{ route('admin.sales.index', ['status' => 'refunded']) }}" class="mini-kpi">
                    <span class="mini-kpi-icon"><x-icon name="refresh" class="w-4 h-4" /></span>
                    <div class="body">
                        <div class="flex items-center justify-between gap-2">
                            <div class="label">{{ __('admin.dashboard.kpi.refunds') }}</div>
                            <span class="trend-chip trend-down">−0%</span>
                        </div>
                        <div class="num tnum">{{ format_money($todayRefunds) }}</div>
                    </div>
                    <div class="spark" x-html="miniSparkSmooth(txnByDay.map(v => v * 0.04).reverse(), 'var(--danger)')"></div>
                </a>
            </div>
        </div>

        {{-- ══ ROW 2 — Operations strip ════════════════════════════════ --}}
        <div class="section-head dash-section">
            <div class="h">{{ __('admin.dashboard.ops.title') }}</div>
            <div class="meta">{{ __('admin.dashboard.ops.realtime') }}</div>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <a href="{{ route('admin.inventory.low-stock.index') }}" class="ops-tile {{ $lowStockCount > 0 ? 'is-critical' : '' }} no-underline">
                <span class="icon-circle danger-soft danger"><x-icon name="alert" class="w-5 h-5" /></span>
                <div class="flex-1 min-w-0">
                    <div class="num {{ $lowStockCount > 0 ? 'danger' : '' }}">{{ $lowStockCount }}</div>
                    <div class="lbl">{{ __('admin.dashboard.ops.items_below_reorder') }}</div>
                </div>
                <x-icon name="chevron-right" class="w-4 h-4 fg-tertiary" />
            </a>
            {{-- Oversold: only when it's actually happening (an exceptional state
                 under the overselling policy) so the normal strip stays clean. --}}
            @if ($oversoldCount > 0)
                <a href="{{ route('admin.inventory.oversold.index') }}" class="ops-tile is-critical no-underline">
                    <span class="icon-circle danger-soft danger"><x-icon name="alert" class="w-5 h-5" /></span>
                    <div class="flex-1 min-w-0">
                        <div class="num danger">{{ $oversoldCount }}</div>
                        <div class="lbl">{{ __('admin.dashboard.ops.items_oversold') }}</div>
                    </div>
                    <x-icon name="chevron-right" class="w-4 h-4 fg-tertiary" />
                </a>
            @endif
            <a href="{{ route('admin.purchases.index') }}" class="ops-tile {{ $posInTransit > 0 ? 'is-warning' : '' }} no-underline">
                <span class="icon-circle warning-soft warning"><x-icon name="truck" class="w-5 h-5" /></span>
                <div class="flex-1 min-w-0">
                    <div class="num">{{ $posInTransit }}</div>
                    <div class="lbl">
                        {{ __('admin.dashboard.ops.pos_in_transit') }}
                        @if($posInTransitValue > 0)
                            · {{ format_money($posInTransitValue) }}
                        @endif
                    </div>
                </div>
                <x-icon name="chevron-right" class="w-4 h-4 fg-tertiary" />
            </a>
            <div class="ops-tile">
                <span class="icon-circle positive-soft positive"><x-icon name="cash" class="w-5 h-5" /></span>
                <div class="flex-1 min-w-0">
                    <div class="num tnum">{{ format_money($todayRevenue) }}</div>
                    <div class="lbl">{{ __('admin.dashboard.ops.open_till') }}</div>
                </div>
            </div>
            <div class="ops-tile">
                <span class="icon-circle accent-soft accent"><x-icon name="customers" class="w-5 h-5" /></span>
                <div class="flex-1 min-w-0">
                    <div class="num tnum">{{ $cashiersOnShift }}</div>
                    <div class="lbl">{{ __('admin.dashboard.ops.cashiers_on_shift') }}</div>
                </div>
            </div>
        </div>

        {{-- ══ ROW 3 — Performance ══════════════════════════════════════ --}}
        <div class="section-head dash-section flex items-center justify-between">
            <div class="h">{{ __('admin.dashboard.performance.title') }}</div>
            <div class="var-seg">
                <button :class="perfRange === 'today' && 'is-active'" @click="perfRange='today'">{{ __('admin.dashboard.performance.today') }}</button>
                <button :class="perfRange === '7d'    && 'is-active'" @click="perfRange='7d'">7d</button>
                <button :class="perfRange === '14d'   && 'is-active'" @click="perfRange='14d'">14d</button>
                <button :class="perfRange === '30d'   && 'is-active'" @click="perfRange='30d'">30d</button>
            </div>
        </div>
        <div class="grid grid-cols-12 gap-3" :class="rangeLoading && 'perf-loading'">

            {{-- Net sales chart --}}
            <div class="bento col-span-12 lg:col-span-8">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('admin.dashboard.performance.net_sales') }}</div>
                        <div class="text-[11.5px] fg-tertiary mt-0.5"
                             x-text="{
                                today: '{{ __('admin.dashboard.performance.period_today') }}',
                                '7d':  '{{ __('admin.dashboard.performance.period_7d') }}',
                                '14d': '{{ __('admin.dashboard.performance.period_label') }}',
                                '30d': '{{ __('admin.dashboard.performance.period_30d') }}'
                             }[perfRange]"></div>
                    </div>
                    <div class="flex items-center gap-3 text-[11.5px] fg-secondary">
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-sm dash-swatch-current"></span>
                            {{ __('admin.dashboard.performance.this') }}
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-sm dash-swatch-prev"></span>
                            {{ __('admin.dashboard.performance.previous') }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="perf-kpi-grid">
                        <div>
                            <div class="eyebrow text-[10.5px]">{{ __('admin.dashboard.performance.net_sales') }}</div>
                            <div class="flex items-baseline gap-2 mt-1.5">
                                <span class="text-[22px] font-semibold tnum fg-primary">{{ format_money($period14Sales) }}</span>
                                <span class="trend-chip {{ $netTrendChip['class'] }}">{{ $netTrendChip['label'] }}</span>
                            </div>
                        </div>
                        <div>
                            <div class="eyebrow text-[10.5px]">{{ __('admin.dashboard.performance.avg_basket') }}</div>
                            <div class="flex items-baseline gap-2 mt-1.5">
                                <span class="text-[22px] font-semibold tnum fg-primary">{{ format_money($period14Avg) }}</span>
                                <span class="trend-chip {{ $basketTrendChip['class'] }}">{{ $basketTrendChip['label'] }}</span>
                            </div>
                        </div>
                        <div>
                            <div class="eyebrow text-[10.5px]">{{ __('admin.dashboard.performance.gross_margin') }}</div>
                            <div class="flex items-baseline gap-2 mt-1.5">
                                <span class="text-[22px] font-semibold tnum fg-primary">{{ $period14MarginLabel }}</span>
                                <span class="trend-chip {{ $marginTrendChip['class'] }}">{{ $marginTrendChip['label'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div x-html="netSalesChart()"
                         @mousemove="onChartMove($event)" @mouseleave="onChartLeave()"></div>
                </div>
            </div>

            {{-- Category mix donut --}}
            <div class="bento col-span-12 lg:col-span-4">
                <div class="card-header">
                    <div class="card-title">{{ __('admin.dashboard.performance.category_mix') }}</div>
                    <span class="text-[11.5px] fg-tertiary" x-text="periodLabel(perfRange)"></span>
                </div>
                <div class="card-body flex flex-col gap-4 items-center">
                    <div x-html="donutChart()"></div>
                    <div class="w-full space-y-1.5">
                        {{-- Show every category the donut represents so the listed
                             amounts sum to the donut's centre total. --}}
                        <template x-for="(c, idx) in perfCategories" :key="c.name">
                            <div class="flex items-center gap-2.5 text-[12px]">
                                <span class="w-2.5 h-2.5 rounded-sm flex-shrink-0" :style="'background:' + donutColor(idx)"></span>
                                <span class="flex-1 fg-secondary truncate" x-text="c.name"></span>
                                <span class="tnum fg-primary font-medium" x-text="$formatMoney(c.rev)"></span>
                            </div>
                        </template>
                        <template x-if="!perfCategories.length">
                            <div class="widget-empty">
                                <span>{{ __('admin.dashboard.no_data') }}</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ ROW 4 — Catalog insights ════════════════════════════════ --}}
        <div class="section-head dash-section flex items-center justify-between">
            <div class="h">{{ __('admin.dashboard.catalog.title') }}</div>
            <div class="flex items-center gap-3">
                <div class="var-seg" :class="catalogLoading && 'opacity-50 pointer-events-none'">
                    <button :class="catalogRange === 'today' && 'is-active'" @click="catalogRange='today'">{{ __('admin.dashboard.performance.today') }}</button>
                    <button :class="catalogRange === '7d'    && 'is-active'" @click="catalogRange='7d'">7d</button>
                    <button :class="catalogRange === '14d'   && 'is-active'" @click="catalogRange='14d'">14d</button>
                    <button :class="catalogRange === '30d'   && 'is-active'" @click="catalogRange='30d'">30d</button>
                </div>
                <a href="{{ route('admin.products.index') }}" class="text-[11.5px] accent hover:underline">{{ __('admin.dashboard.manage_products') }}</a>
            </div>
        </div>
        <div class="grid grid-cols-12 gap-3">

            {{-- Top products --}}
            <div class="bento col-span-12 md:col-span-6 lg:col-span-4">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('admin.dashboard.catalog.top_products') }}</div>
                        <div class="text-[11.5px] fg-tertiary mt-0.5" x-text="periodLabel(catalogRange)"></div>
                    </div>
                    <a href="{{ route('admin.sales.index') }}" class="text-[11.5px] fg-tertiary hover:fg-primary">{{ __('admin.dashboard.see_all') }}</a>
                </div>
                <template x-if="catalogProducts.length === 0">
                    <div class="widget-empty">
                        <x-icon name="box" class="w-8 h-8 fg-tertiary opacity-40" />
                        <span>{{ __('admin.dashboard.no_data') }}</span>
                    </div>
                </template>
                <template x-for="(p, idx) in catalogProducts.slice(0, 6)" :key="p.id">
                    <div class="tp-row">
                        <span class="tp-rank" :class="idx === 0 && 'is-top'" x-text="(idx + 1).toString().padStart(2, '0')"></span>
                        <div class="product-thumb tp-thumb">
                            <template x-if="p.image">
                                <img :src="p.image" :alt="p.name" />
                            </template>
                            <template x-if="!p.image">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 20 20"><rect x="3" y="3" width="14" height="14" rx="3" stroke="currentColor" stroke-width="1.5"/></svg>
                            </template>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[13px] font-medium fg-primary truncate" x-text="p.name"></div>
                            <div class="text-[11px] fg-tertiary mt-0.5 flex items-center gap-1 flex-wrap">
                                <span class="tnum" x-text="p.units.toFixed(0)"></span>
                                <span>{{ __('admin.dashboard.sold') }}</span>
                                <template x-if="p.category">
                                    <span class="flex items-center gap-1">
                                        <span class="fg-tertiary">·</span>
                                        <span x-text="p.category"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                        {{-- 7-day mini trend bars --}}
                        <div class="tp-bars shrink-0">
                            <template x-for="(v, bi) in (p.trend ?? [])" :key="bi">
                                <div class="tp-bar"
                                     :class="bi === (p.trend.length - 1) && 'is-peak'"
                                     :style="'height:' + Math.max(3, Math.round(v / Math.max(...(p.trend), 1) * 22)) + 'px'">
                                </div>
                            </template>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-[13px] font-semibold tnum fg-primary" x-text="$formatMoney(p.revenue)"></div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Low stock --}}
            <div class="bento col-span-12 md:col-span-6 lg:col-span-4">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('admin.dashboard.catalog.low_stock') }}</div>
                        <div class="text-[11.5px] fg-tertiary mt-0.5">{{ __('admin.dashboard.catalog.low_stock_sub') }}</div>
                    </div>
                    <a href="{{ route('admin.inventory.low-stock.index') }}" class="text-[11.5px] accent hover:underline">{{ __('admin.dashboard.reorder_all') }}</a>
                </div>
                <template x-if="lowStockItems.length === 0">
                    <div class="widget-empty">
                        <x-icon name="box" class="w-8 h-8 fg-tertiary opacity-40" />
                        <span class="positive">{{ __('admin.dashboard.no_data') }}</span>
                    </div>
                </template>
                <template x-for="row in lowStockItems.slice(0, 5)" :key="row.id">
                    <div class="ls-row">
                        <div class="product-thumb">
                            <template x-if="row.image">
                                <img :src="row.image" :alt="row.name" />
                            </template>
                            <template x-if="!row.image">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 16 16"><rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/></svg>
                            </template>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[13px] font-medium fg-primary truncate" x-text="row.name"></div>
                            <div class="progress mt-1.5 w-full max-w-[120px]"
                                 :class="row.qty <= row.reorderAt ? 'is-low' : (row.qty < row.parLevel * 0.6 ? 'is-med' : 'is-good')">
                                <span :style="'width:' + Math.min(100, (row.qty / Math.max(row.parLevel, 1)) * 100) + '%'"></span>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="tnum text-[12.5px] font-semibold"
                                  :class="row.qty <= row.reorderAt ? 'danger' : 'fg-primary'"
                                  x-text="row.qty.toFixed(0)"></span>
                            <div class="text-[10.5px] fg-tertiary tnum">of <span x-text="row.parLevel.toFixed(0)"></span></div>
                        </div>
                        <a href="{{ route('admin.purchases.create') }}" class="pos-btn pos-btn-xs" @click.stop>
                            {{ __('admin.dashboard.reorder') }}
                        </a>
                    </div>
                </template>
            </div>

            {{-- Activity feed --}}
            <div class="bento col-span-12 lg:col-span-4">
                <div class="card-header">
                    <div class="card-title">{{ __('admin.dashboard.catalog.activity') }}</div>
                    <span class="live-pulse">{{ __('admin.dashboard.live_label') }}</span>
                </div>
                <template x-if="activity.length === 0">
                    <div class="widget-empty">
                        <x-icon name="list" class="w-8 h-8 fg-tertiary opacity-40" />
                        <span>{{ __('admin.dashboard.no_data') }}</span>
                    </div>
                </template>
                <template x-for="(a, idx) in activity.slice(0, 6)" :key="idx">
                    <div class="act-row">
                        <span class="activity-ico">
                            <x-icon name="receipt" class="w-3.5 h-3.5" />
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="text-[12.5px] fg-primary leading-snug">
                                <span class="font-medium" x-text="a.who"></span>
                                <span class="fg-secondary"> </span>
                                <span class="fg-secondary" x-text="a.what"></span>
                                <span class="fg-secondary"> </span>
                                <span class="font-medium" x-text="a.target"></span>
                            </div>
                            <div class="text-[10.5px] fg-tertiary mt-1" x-text="a.when"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ══ ROW 5 — Heatmap + Shift ═════════════════════════════════ --}}
        <div class="section-head dash-section">
            <div class="h">{{ __('admin.dashboard.insights.title') }}</div>
            <a href="{{ route('admin.reports.index') }}" class="text-[11.5px] accent hover:underline">{{ __('admin.dashboard.all_reports') }}</a>
        </div>
        <div class="grid grid-cols-12 gap-3 mb-6">

            {{-- Heatmap --}}
            <div class="bento col-span-12 lg:col-span-7">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('admin.dashboard.insights.heatmap_title') }}</div>
                        <div class="text-[11.5px] fg-tertiary mt-0.5">{{ __('admin.dashboard.insights.heatmap_sub') }}</div>
                    </div>
                    <div class="flex items-center gap-2 text-[10.5px] fg-tertiary">
                        {{ __('admin.dashboard.insights.legend_low') }}
                        <div class="flex gap-0.5">
                            <span class="w-3 h-3 rounded-sm dash-heat-1"></span>
                            <span class="w-3 h-3 rounded-sm dash-heat-2"></span>
                            <span class="w-3 h-3 rounded-sm dash-heat-3"></span>
                            <span class="w-3 h-3 rounded-sm dash-heat-4"></span>
                            <span class="w-3 h-3 rounded-sm dash-heat-5"></span>
                        </div>
                        {{ __('admin.dashboard.insights.legend_high') }}
                    </div>
                </div>
                <div class="card-body">
                    <div class="text-[12px] fg-tertiary tnum mb-2 tracking-wide">00:00 — 23:59</div>
                    <div class="dash-scroll-x">
                        <div class="dash-heat-min" x-html="heatmap()"
                             @mousemove="onChartMove($event)" @mouseleave="onChartLeave()"></div>
                    </div>
                </div>
            </div>

            {{-- Shift staff --}}
            <div class="bento col-span-12 lg:col-span-5">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('admin.dashboard.insights.shift_title') }}</div>
                        <div class="text-[11.5px] fg-tertiary mt-0.5">{{ __('admin.dashboard.insights.shift_sub') }}</div>
                    </div>
                    <a href="{{ route('admin.users.index') }}" class="text-[11.5px] fg-tertiary hover:fg-primary">{{ __('admin.dashboard.manage') }}</a>
                </div>
                <div class="dash-scroll-x">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.dashboard.insights.shift_staff') }}</th>
                            <th>{{ __('admin.dashboard.insights.shift_status') }}</th>
                            <th class="num">{{ __('admin.dashboard.insights.shift_sales') }}</th>
                            <th class="num">{{ __('admin.dashboard.insights.shift_txns') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="shiftStaff.length === 0">
                            <tr>
                                <td colspan="4" class="text-center fg-tertiary text-[12px] py-6">{{ __('admin.dashboard.no_data') }}</td>
                            </tr>
                        </template>
                        <template x-for="s in shiftStaff" :key="s.id">
                            <tr>
                                <td>
                                    <div class="name-cell">
                                        <div class="shift-avatar" x-text="s.name.split(' ').map(n => n[0]).join('').slice(0,2)"></div>
                                        <div>
                                            <div class="text-[13px] font-medium fg-primary" x-text="s.name"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" :class="s.status === 'on' ? 'badge-positive' : ''">
                                        <span class="badge-dot"></span>
                                        <span x-text="s.status === 'on' ? '{{ __('admin.dashboard.insights.staff_on') }}' : '{{ __('admin.dashboard.insights.staff_off') }}'"></span>
                                    </span>
                                </td>
                                <td class="num tnum" x-text="$formatMoney(s.sales)"></td>
                                <td class="num tnum" x-text="s.txns"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        {{-- Floating chart tooltip — must stay inside x-data scope.
             `tip.card` renders the rich heatmap card; otherwise the simple
             label/value/sub lines used by the other charts. --}}
        <div x-show="tip.show" x-cloak
             class="chart-tip"
             :class="tip.card ? 'chart-tip-card' : ''"
             :style="`left:${tip.x + 14}px;top:${tip.y - 60}px`">

            {{-- Rich card (heatmap) --}}
            <template x-if="tip.card">
                <div>
                    <div class="chart-tip-head">
                        <span class="chart-tip-head-title" x-text="tip.card.title"></span>
                        <span class="chart-tip-head-meta" x-text="tip.card.meta"></span>
                    </div>
                    <div class="chart-tip-headline">
                        <span class="chart-tip-headline-label">
                            <span class="chart-tip-dot"></span>
                            <span x-text="tip.card.headline.label"></span>
                        </span>
                        <span class="chart-tip-headline-val" x-text="tip.card.headline.value"></span>
                    </div>
                    <div class="chart-tip-rows">
                        <template x-for="(row, i) in tip.card.rows" :key="i">
                            <div class="chart-tip-row">
                                <span class="chart-tip-row-label" x-text="row.label"></span>
                                <span class="chart-tip-row-val" x-text="row.value"></span>
                            </div>
                        </template>
                    </div>
                    <div class="chart-tip-foot" x-text="tip.card.footer"></div>
                </div>
            </template>

            {{-- Simple lines (other charts) --}}
            <template x-if="!tip.card">
                <div>
                    <template x-for="(line, i) in tip.lines" :key="i">
                        <div :class="i === 0 ? 'chart-tip-label' : (i === 1 ? 'chart-tip-val' : 'chart-tip-sub')"
                             x-text="line"></div>
                    </template>
                </div>
            </template>
        </div>

    </div>{{-- /page-wide --}}
</x-admin-layout>
