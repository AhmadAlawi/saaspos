<x-admin-layout
    active="stock-takes"
    :title="__('stock_takes.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('stock_takes.crumb_parent')],
        ['label' => __('stock_takes.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('stock_takes.title') }}</h1>
                <p class="page-sub">{{ __('stock_takes.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('stock_takes.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.inventory.stock-takes.export', ['format' => 'csv']) }}"
                           class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('stock_takes.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.inventory.stock-takes.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('stock_takes.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                @can('create', App\Models\StockTake::class)
                    <a href="{{ route('admin.inventory.stock-takes.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('stock_takes.new') }}
                    </a>
                @endcan
            </div>
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        <div class="card card-pad-0"
             x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">{{ __('stock_takes.list_title') }} (<span x-text="rowCount">{{ $takes->count() }}</span>)</div>
                <x-admin.dt-toolbar-actions />
            </div>
            <form method="GET" action="{{ route('admin.inventory.stock-takes.index') }}" class="inv-filter">
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('stock_takes.columns.store') }}</span>
                    <select name="store_id" class="pos-input" x-data="enhancedSelect()" onchange="this.form.submit()">
                        <option value="">{{ __('stock_takes.filter.store_all') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected($filters['storeId'] === $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('stock_takes.columns.status') }}</span>
                    <select name="status" class="pos-input" x-data="enhancedSelect()" onchange="this.form.submit()">
                        <option value="">{{ __('stock_takes.filter.status_all') }}</option>
                        <option value="draft"     @selected($filters['status'] === 'draft')>{{ __('stock_takes.status.draft') }}</option>
                        <option value="posted"    @selected($filters['status'] === 'posted')>{{ __('stock_takes.status.posted') }}</option>
                        <option value="cancelled" @selected($filters['status'] === 'cancelled')>{{ __('stock_takes.status.cancelled') }}</option>
                    </select>
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('stock_takes.filter.date_from') }}</span>
                    <input type="text" name="from" value="{{ $filters['from'] }}"
                           class="pos-input js-datepicker"
                           data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('stock_takes.filter.date_to') }}</span>
                    <input type="text" name="to" value="{{ $filters['to'] }}"
                           class="pos-input js-datepicker"
                           data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" name="q" value="{{ $filters['q'] }}" class="pos-input"
                               placeholder="{{ __('stock_takes.filter.search') }}">
                    </span>
                </label>
                <x-admin.filter-reset route="admin.inventory.stock-takes.index" />
            </form>

            @if ($takes->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="edit" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('stock_takes.empty_state.title') }}</div>
                    <div class="dt-empty-sub">{{ __('stock_takes.empty_state.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('stock_takes.columns.number') }}</th>
                            <th>{{ __('stock_takes.columns.name') }}</th>
                            <th>{{ __('stock_takes.columns.date') }}</th>
                            <th>{{ __('stock_takes.columns.store') }}</th>
                            <th class="num">{{ __('stock_takes.columns.items') }}</th>
                            <th>{{ __('stock_takes.columns.status') }}</th>
                            <th>{{ __('stock_takes.columns.by') }}</th>
                            <th class="dt-actions-col"><span class="sr-only">{{ __('stock_takes.columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($takes as $take)
                            <tr class="prod-row" data-dt-row
                                data-dt-name="{{ $take->number ?? '' }}"
                                data-dt-id="{{ $take->id }}"
                                @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.inventory.stock-takes.' . ($take->isDraft() ? 'edit' : 'show'), $take)) }}">
                                <td class="mono">{{ $take->number }}</td>
                                <td>{{ $take->name ?: '—' }}</td>
                                <td>{{ format_date($take->take_date) }}</td>
                                <td>{{ $take->store?->name }}</td>
                                <td class="num tnum">{{ $take->items_count }}</td>
                                <td>
                                    @if ($take->isPosted())
                                        <span class="prod-badge prod-badge-positive">{{ __('stock_takes.status.posted') }}</span>
                                    @elseif ($take->isCancelled())
                                        <span class="prod-badge prod-badge-muted">{{ __('stock_takes.status.cancelled') }}</span>
                                    @else
                                        <span class="prod-badge prod-badge-warning">{{ __('stock_takes.status.draft') }}</span>
                                    @endif
                                </td>
                                <td>{{ $take->isPosted() ? ($take->poster?->name ?? '—') : ($take->creator?->name ?? '—') }}</td>
                                <td>
                                    <x-admin.row-actions>
                                        @if ($take->isDraft())
                                            <x-admin.row-action :href="route('admin.inventory.stock-takes.edit', $take)" icon="edit" :label="__('stock_takes.actions.edit')" />
                                            <div class="row-action-sep"></div>
                                            <x-admin.row-action icon="trash" variant="danger" :label="__('stock_takes.actions.delete')"
                                                @click="$store.confirm.show({
                                                    title: {{ \Illuminate\Support\Js::from(__('stock_takes.confirm_delete.title', ['number' => $take->number])) }},
                                                    message: {{ \Illuminate\Support\Js::from(__('stock_takes.confirm_delete.message')) }},
                                                    intent: 'danger',
                                                    confirmLabel: {{ \Illuminate\Support\Js::from(__('stock_takes.confirm_delete.confirm')) }},
                                                    onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.inventory.stock-takes.destroy', $take)) }}),
                                                })" />
                                        @else
                                            <x-admin.row-action :href="route('admin.inventory.stock-takes.show', $take)" icon="eye" :label="__('stock_takes.actions.view')" />
                                        @endif
                                    </x-admin.row-actions>
                                </td>
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
