<x-admin-layout
    active="customers"
    :title="__('customers.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('customers.crumb_parent')],
        ['label' => __('customers.title')],
    ]">

    {{-- Server-paginated. Only the first page is rendered below; every search /
         filter / sort / page fetches one page of rows from `admin.customers.rows`.
         See resources/js/admin/data-table-server.js. --}}
    <div class="page-wide" x-data="customersIndexPage({{ \Illuminate\Support\Js::from([
        'endpoint'          => route('admin.customers.rows'),
        'toggleUrlTemplate' => route('admin.customers.toggle-active', ['customer' => '__ID__']),
        'perPage'           => $perPage,
        'total'             => $total,
        'page'              => 1,
        'totalPages'        => $totalPages,
    ]) }})">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('customers.title') }}</h1>
                <p class="page-sub">{{ __('customers.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('customers.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.customers.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('customers.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.customers.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('customers.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                @can('create', App\Models\Customer::class)
                    <a href="{{ route('admin.customers.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('customers.new') }}
                    </a>
                @endcan
            </div>
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        {{-- "No customers at all" — distinct from "no rows match the filters",
             which the data-table's own `.dt-empty` handles. Keyed on the
             unfiltered count so it never shows just because a filter is on. --}}
        @if ($total === 0)
            <div class="card card-pad-0">
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="customers" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('customers.empty_state.title') }}</div>
                    <div class="dt-empty-sub">{{ __('customers.empty_state.sub') }}</div>
                </div>
            </div>
        @else
            {{-- ── Filter bar ──────────────────────────────────────── --}}
            <div class="inv-filter inv-filter--boxed mb-3">
                <label class="field inv-filter-search">
                    <span class="field-label">{{ __('table.search_label') }}</span>
                    <div class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        {{-- No `.debounce` modifier: the mixin debounces the
                             fetch itself, and stacking the two would add up. --}}
                        <input type="search"
                               x-model="search"
                               placeholder="{{ __('customers.filter.search') }}"
                               class="pos-input">
                    </div>
                </label>

                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('customers.columns.status') }}</span>
                    <select class="pos-input"
                            x-data="enhancedSelect()"
                            x-model="statusFilter"
                            x-effect="ts && ts.setValue(statusFilter)">
                        <option value="all">{{ __('customers.filter.status_all') }}</option>
                        <option value="active">{{ __('customers.filter.status_active') }}</option>
                        <option value="inactive">{{ __('customers.filter.status_inactive') }}</option>
                    </select>
                </label>

                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('customers.columns.group') }}</span>
                    <select class="pos-input"
                            x-data="enhancedSelect()"
                            x-model="groupFilter"
                            x-effect="ts && ts.setValue(groupFilter)">
                        <option value="all">{{ __('customers.filter.group_all') }}</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </label>

                <button type="button" class="inv-filter-reset" @click="resetFilters()"
                        title="{{ __('table.filter_reset') }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    <span>{{ __('table.filter_reset') }}</span>
                </button>
            </div>

            {{-- ── Table ───────────────────────────────────────────── --}}
            <x-admin.data-table :show-search="false">
                <x-slot:toolbar-start>
                    <div class="dt-toolbar-title">
                        <span x-text="matchedCount.toLocaleString()"></span>
                        {{ __('customers.list.of') }}
                        <span>{{ number_format($total) }}</span>
                    </div>
                </x-slot:toolbar-start>

                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th class="dt-th-sort"
                                @click="sortBy('code')"
                                :class="{ 'is-sorted-asc': sortDirFor('code') === 'asc', 'is-sorted-desc': sortDirFor('code') === 'desc' }">
                                {{ __('customers.columns.code') }}
                                <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                            </th>
                            <th class="dt-th-sort"
                                @click="sortBy('name')"
                                :class="{ 'is-sorted-asc': sortDirFor('name') === 'asc', 'is-sorted-desc': sortDirFor('name') === 'desc' }">
                                {{ __('customers.columns.name') }}
                                <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                            </th>
                            <th>{{ __('customers.columns.phone') }}</th>
                            <th>{{ __('customers.columns.email') }}</th>
                            <th>{{ __('customers.columns.group') }}</th>
                            <th class="num dt-th-sort"
                                @click="sortBy('outstanding')"
                                :class="{ 'is-sorted-asc': sortDirFor('outstanding') === 'asc', 'is-sorted-desc': sortDirFor('outstanding') === 'desc' }">
                                {{ __('customers.columns.outstanding') }}
                                <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                            </th>
                            <th class="num dt-th-sort"
                                @click="sortBy('credit')"
                                :class="{ 'is-sorted-asc': sortDirFor('credit') === 'asc', 'is-sorted-desc': sortDirFor('credit') === 'desc' }">
                                {{ __('customers.columns.credit') }}
                                <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                            </th>
                            <th class="dt-th-sort"
                                @click="sortBy('status')"
                                :class="{ 'is-sorted-asc': sortDirFor('status') === 'asc', 'is-sorted-desc': sortDirFor('status') === 'desc' }">
                                {{ __('customers.columns.status') }}
                                <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                            </th>
                            <th class="dt-actions-col"><span class="sr-only">{{ __('customers.columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.customers._rows', ['customers' => $customers])
                    </tbody>
                </table>
                </div>
            </x-admin.data-table>
        @endif
    </div>
</x-admin-layout>
