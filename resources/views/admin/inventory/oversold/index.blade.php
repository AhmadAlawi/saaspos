<x-admin-layout
    active="oversold"
    :title="__('inventory.oversold.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('inventory.nav.section')],
        ['label' => __('inventory.oversold.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('inventory.oversold.title') }}</h1>
                <p class="page-sub">{{ __('inventory.oversold.sub') }}</p>
            </div>
            @if ($rows->isNotEmpty())
                <div class="flex items-center gap-2">
                    <div class="dropdown" x-data="dropdown">
                        <button type="button"
                                class="pos-btn pos-btn-sm pos-btn-ghost"
                                @click="toggle()"
                                :aria-expanded="open">
                            <x-icon name="download" class="w-4 h-4" />
                            {{ __('inventory.oversold.actions.export') }}
                            <x-icon name="chevron" class="w-4 h-4" />
                        </button>
                        <div class="dropdown-panel"
                             x-show="open"
                             x-cloak
                             @click.outside="close()"
                             @keydown.escape.window="close()">
                            <a href="{{ route('admin.inventory.oversold.export', array_filter(['format' => 'csv', 'store_id' => $storeId])) }}"
                               class="dropdown-item" @click="close()">
                                <span class="dropdown-item-label">{{ __('inventory.oversold.actions.export_csv') }}</span>
                            </a>
                            <a href="{{ route('admin.inventory.oversold.export', array_filter(['format' => 'xlsx', 'store_id' => $storeId])) }}"
                               class="dropdown-item" @click="close()">
                                <span class="dropdown-item-label">{{ __('inventory.oversold.actions.export_xlsx') }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <x-alert type="info" class="mb-4">{{ __('inventory.oversold.explainer') }}</x-alert>

        <x-admin.summary-cards :cards="$summaryCards" />

        <div class="card card-pad-0"
             x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">{{ __('inventory.oversold.list_title', ['count' => $rows->count()]) }}</div>
                <x-admin.dt-toolbar-actions />
            </div>
            <form method="GET" action="{{ route('admin.inventory.oversold.index') }}" class="inv-filter">
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('inventory.oversold.columns.store') }}</span>
                    <select name="store_id" class="pos-input" x-data="enhancedSelect()" onchange="this.form.submit()">
                        <option value="">{{ __('inventory.oversold.filter.store_all') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected($storeId === $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" x-model.debounce.150ms="search"
                               class="pos-input"
                               placeholder="{{ __('inventory.oversold.filter.search') }}">
                    </span>
                </label>
                <x-admin.filter-reset route="admin.inventory.oversold.index" />
            </form>

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="check" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('inventory.oversold.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('inventory.oversold.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('inventory.oversold.columns.product') }}</th>
                            <th>{{ __('inventory.oversold.columns.sku') }}</th>
                            <th>{{ __('inventory.oversold.columns.store') }}</th>
                            <th class="num">{{ __('inventory.oversold.columns.on_hand') }}</th>
                            <th class="num">{{ __('inventory.oversold.columns.oversold_by') }}</th>
                            <th>{{ __('inventory.oversold.columns.last_movement') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr data-dt-row
                                data-dt-name="{{ $row['product'] }}"
                                data-dt-id="">
                                <td>
                                    <div class="store-row-name-line">
                                        <span class="store-row-name">{{ $row['product'] }}</span>
                                        @if ($row['variant_label']) <span class="fg-tertiary">· {{ $row['variant_label'] }}</span> @endif
                                        <span class="prod-badge prod-badge-danger">{{ __('inventory.oversold.badge') }}</span>
                                    </div>
                                </td>
                                <td class="mono">{{ $row['sku'] }}</td>
                                <td>{{ $row['store'] }}</td>
                                <td class="num tnum inv-delta-neg">−{{ rtrim(rtrim(number_format(-$row['on_hand'], 4, '.', ''), '0'), '.') ?: '0' }}</td>
                                <td class="num tnum inv-delta-neg">{{ rtrim(rtrim(number_format($row['oversold_by'], 4, '.', ''), '0'), '.') ?: '0' }}</td>
                                <td class="fg-tertiary">{{ $row['last_movement'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

                <x-admin.dt-pager />
            @endif
        </div>
    </div>
</x-admin-layout>
