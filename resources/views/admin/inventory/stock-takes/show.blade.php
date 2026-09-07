<x-admin-layout
    active="stock-takes"
    :title="$take->number"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('stock_takes.crumb_parent')],
        ['label' => __('stock_takes.title'), 'href' => route('admin.inventory.stock-takes.index')],
        ['label' => $take->number],
    ]">

    @php
        $items = $take->items;
        $countedLines  = $items->whereNotNull('counted_quantity')->count();
        $varianceLines = $items->filter(fn ($i) => $i->hasVariance())->count();
        $netVariance = $items->reduce(function ($carry, $i) {
            $v = $i->variance();
            return $v !== null ? bcadd($carry, $v, 4) : $carry;
        }, '0');
    @endphp

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.inventory.stock-takes.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('stock_takes.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ $take->number }}</h1>
                    <p class="page-sub">
                        <span>{{ $take->store?->name }}</span>
                        <span class="fg-tertiary">·</span>
                        <span>{{ format_date($take->take_date) }}</span>
                        <span class="fg-tertiary">·</span>
                        @if ($take->isPosted())
                            <span class="prod-badge prod-badge-positive">{{ __('stock_takes.status.posted') }}</span>
                        @elseif ($take->isCancelled())
                            <span class="prod-badge prod-badge-muted">{{ __('stock_takes.status.cancelled') }}</span>
                        @else
                            <span class="prod-badge prod-badge-warning">{{ __('stock_takes.status.draft') }}</span>
                        @endif
                    </p>
                </div>
            </div>
            @if ($take->isDraft())
                <a href="{{ route('admin.inventory.stock-takes.edit', $take) }}" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="edit" class="w-4 h-4" />
                    {{ __('stock_takes.actions.continue_count') }}
                </a>
            @endif
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('stock_takes.kpis.lines_total') }}</div>
                <div class="cust-kpi-value num tnum">{{ $items->count() }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('stock_takes.kpis.lines_counted') }}</div>
                <div class="cust-kpi-value num tnum">{{ $countedLines }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('stock_takes.kpis.lines_with_variance') }}</div>
                <div class="cust-kpi-value num tnum">{{ $varianceLines }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('stock_takes.kpis.net_variance') }}</div>
                <div class="cust-kpi-value num tnum
                     @if (bccomp($netVariance, '0', 4) < 0) text-amber-600 dark:text-amber-400
                     @elseif (bccomp($netVariance, '0', 4) > 0) text-emerald-600 dark:text-emerald-400 @endif">
                    {{ bccomp($netVariance, '0', 4) > 0 ? '+' : '' }}{{ rtrim(rtrim($netVariance, '0'), '.') }}
                </div>
            </div></div>
        </div>

        @if ($take->name || $take->notes)
            <div class="card mb-5"><div class="card-body">
                @if ($take->name)
                    <div class="mb-2"><span class="fg-tertiary">{{ __('stock_takes.fields.name') }}:</span> <span>{{ $take->name }}</span></div>
                @endif
                @if ($take->notes)
                    <div><span class="fg-tertiary">{{ __('stock_takes.fields.notes') }}:</span> <span>{{ $take->notes }}</span></div>
                @endif
            </div></div>
        @endif

        <div class="card card-pad-0"
             x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">{{ __('stock_takes.items_title') }} (<span x-text="rowCount">{{ $items->count() }}</span>)</div>
                <x-admin.dt-toolbar-actions />
            </div>

            <table class="dt-table">
                <thead>
                    <tr>
                        <th>{{ __('stock_takes.items.product') }}</th>
                        <th class="num">{{ __('stock_takes.items.expected') }}</th>
                        <th class="num">{{ __('stock_takes.items.counted') }}</th>
                        <th class="num">{{ __('stock_takes.items.variance') }}</th>
                        <th>{{ __('stock_takes.items.notes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        @php
                            $variance = $item->variance();
                            $hasVariance = $item->hasVariance();
                        @endphp
                        <tr data-dt-row data-dt-name="{{ ($item->variant?->sku ?: $item->product?->sku) . ' ' . $item->product?->name }}">
                            <td>
                                <div class="font-medium">{{ $item->product?->name ?? '—' }}</div>
                                <div class="fg-tertiary text-xs mono">{{ $item->variant?->sku ?: $item->product?->sku }}</div>
                            </td>
                            <td class="num tnum">{{ rtrim(rtrim((string) $item->expected_quantity, '0'), '.') }} {{ $item->product?->unit?->code ?: 'pc' }}</td>
                            <td class="num tnum">
                                @if ($item->counted_quantity === null)
                                    <span class="fg-tertiary">—</span>
                                @else
                                    {{ rtrim(rtrim((string) $item->counted_quantity, '0'), '.') }}
                                @endif
                            </td>
                            <td class="num tnum">
                                @if ($variance === null)
                                    <span class="fg-tertiary">—</span>
                                @elseif ($hasVariance)
                                    <span class="@if (bccomp($variance, '0', 4) < 0) text-amber-600 dark:text-amber-400 @else text-emerald-600 dark:text-emerald-400 @endif font-semibold">
                                        {{ bccomp($variance, '0', 4) > 0 ? '+' : '' }}{{ rtrim(rtrim($variance, '0'), '.') }}
                                    </span>
                                @else
                                    <span class="fg-tertiary">0</span>
                                @endif
                            </td>
                            <td>{{ $item->notes ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <x-admin.dt-pager />
        </div>

        @if ($take->isPosted())
            <div class="mt-4 fg-tertiary text-xs">
                {{ __('stock_takes.posted_meta', [
                    'when' => optional($take->posted_at)->toDateTimeString() ?: '',
                    'who'  => $take->poster?->name ?: '—',
                ]) }}
            </div>
        @endif
    </div>
</x-admin-layout>
