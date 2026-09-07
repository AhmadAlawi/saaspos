<x-admin-layout
    active="products"
    :title="__('products.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('products.crumb_parent')],
        ['label' => __('products.title')],
    ]">

    {{-- Server-paginated. Only the first page of the active view is rendered
         below; every search / filter / sort / page fetches one page of rows
         from `admin.products.rows`. See resources/js/admin/data-table-server.js. --}}
    <div class="page-wide" x-data="productsPage({{ \Illuminate\Support\Js::from([
        'endpoint'      => route('admin.products.rows'),
        'view'          => $view,
        'perPage'       => $perPage,
        'tablePageSize' => $tablePageSize,
        'gridPageSize'  => $gridPageSize,
        'total'        => $summary['total'],
        'page'         => 1,
        'totalPages'   => max(1, (int) ceil($summary['total'] / max(1, $perPage))),
        'grandTotal'   => $grandTotal,
        'summary'      => $summary,
    ]) }})">

        {{-- ── Page header (mirrors design-system/admin/Products.html) ── --}}
        <div class="page-header mb-4">
            <div>
                <h1 class="page-title">{{ __('products.title') }}</h1>
                <p class="page-sub">
                    {{ __('products.list.header_sub', ['count' => $grandTotal]) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('products.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.products.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('products.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.products.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('products.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                <a href="{{ route('admin.products.import') }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost">
                    <x-icon name="archive" class="w-4 h-4" />
                    {{ __('products.actions.import') }}
                </a>

                <a href="{{ route('admin.products.bulk-images') }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost">
                    <x-icon name="image" class="w-4 h-4" />
                    {{ __('products.bulk_images.action') }}
                </a>

                <a href="{{ route('admin.products.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('products.actions.new') }}
                </a>
            </div>
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        {{-- "No products at all" — distinct from "no rows match the filters",
             which the data-table's own `.dt-empty` handles. Keyed on the
             unfiltered count so it never shows just because a filter is on. --}}
        @if ($grandTotal === 0)
            <div class="card card-pad-0">
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="box" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('products.list.empty_title') }}</div>
                    <div class="dt-empty-sub">{{ __('products.list.empty_sub') }}</div>
                </div>
            </div>
        @else
            {{-- ── Toolbar row ─────────────────────────────────────── --}}
            <div class="prod-toolbar mb-3">
                <div class="prod-toolbar-end">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            :disabled="_dtRefreshing"
                            @click="dtRefresh()"
                            :title="@js(__('products.list.refresh'))">
                        <span class="inline-flex" :class="{ 'animate-spin': _dtRefreshing }">
                            <x-icon name="refresh" class="w-4 h-4" />
                        </span>
                    </button>

                    <span class="prod-toolbar-count">
                        <span x-text="matchedCount.toLocaleString()"></span>
                        {{ __('products.list.of') }}
                        <span>{{ number_format($grandTotal) }}</span>
                    </span>

                    <div class="seg" role="tablist" aria-label="{{ __('products.list.view_label') }}">
                        <button type="button"
                                role="tab"
                                :aria-selected="view === 'table'"
                                :class="{ 'is-active': view === 'table' }"
                                @click="setView('table')">
                            <x-icon name="list" class="w-3.5 h-3.5" />
                            {{ __('products.list.view_table') }}
                        </button>
                        <button type="button"
                                role="tab"
                                :aria-selected="view === 'grid'"
                                :class="{ 'is-active': view === 'grid' }"
                                @click="setView('grid')">
                            <x-icon name="dashboard" class="w-3.5 h-3.5" />
                            {{ __('products.list.view_grid') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── Filter bar (shared — applies to both table + grid views) ── --}}
            <div class="inv-filter inv-filter--boxed mb-3">
                {{-- Search --}}
                <label class="field inv-filter-search">
                    <span class="field-label">{{ __('table.search_label') }}</span>
                    <div class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        {{-- No `.debounce` modifier: the mixin debounces the
                             fetch itself, and stacking the two would add up. --}}
                        <input type="search"
                               x-model="search"
                               placeholder="{{ __('products.list.search_placeholder') }}"
                               class="pos-input">
                    </div>
                </label>

                {{-- Category --}}
                <label class="field flex-none basis-[200px]">
                    <span class="field-label">{{ __('products.list.col_category') }}</span>
                    <select class="pos-input"
                            x-data="enhancedSelect()"
                            x-model="categoryFilter"
                            x-effect="ts && ts.setValue(categoryFilter)">
                        <option value="all">{{ __('products.list.filter_cat_all') }}</option>
                        @foreach ($filterCategories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </label>

                {{-- Type --}}
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('products.list.col_type') }}</span>
                    <select class="pos-input"
                            x-data="enhancedSelect()"
                            x-model="typeFilter"
                            x-effect="ts && ts.setValue(typeFilter)">
                        <option value="all">{{ __('products.list.filter_type_all') }}</option>
                        <option value="simple">{{ __('products.list.type_simple') }}</option>
                        <option value="variant">{{ __('products.list.type_variant') }}</option>
                        <option value="kit">{{ __('products.list.type_kit') }}</option>
                    </select>
                </label>

                {{-- Status --}}
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('products.list.col_status') }}</span>
                    <select class="pos-input"
                            x-data="enhancedSelect()"
                            x-model="activeFilter"
                            x-effect="ts && ts.setValue(activeFilter)">
                        <option value="all">{{ __('products.list.filter_status_all') }}</option>
                        <option value="active">{{ __('products.list.filter_status_active') }}</option>
                        <option value="inactive">{{ __('products.list.filter_status_inactive') }}</option>
                    </select>
                </label>

                {{-- Featured --}}
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('products.list.featured') }}</span>
                    <select class="pos-input"
                            x-data="enhancedSelect()"
                            x-model="featuredFilter"
                            x-effect="ts && ts.setValue(featuredFilter)">
                        <option value="all">{{ __('products.list.filter_featured_all') }}</option>
                        <option value="featured">{{ __('products.list.filter_featured_only') }}</option>
                    </select>
                </label>

                {{-- Reset --}}
                <button type="button"
                        class="inv-filter-reset"
                        @click="resetFilters()">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    <span>{{ __('table.filter_reset') }}</span>
                </button>
            </div>

            {{-- ── Table view ──────────────────────────────────────── --}}
            {{-- Rendered inline only when it's the active view; otherwise the
                 tbody starts empty and `setView('table')` fetches page 1.
                 `x-cloak` on the inactive view stops it flashing an empty
                 container before Alpine's x-show resolves. --}}
            <div x-show="view === 'table'" @if ($view !== 'table') x-cloak @endif>
                <x-admin.data-table :show-search="false" :show-toolbar="false">
                    <div class="dt-scroll">
                    <table class="dt-table">
                        <thead>
                            <tr>
                                <th class="dt-th-sort"
                                    @click="sortBy('name')"
                                    :class="{ 'is-sorted-asc': sortDirFor('name') === 'asc', 'is-sorted-desc': sortDirFor('name') === 'desc' }">
                                    {{ __('products.list.col_name') }}
                                    <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                                </th>
                                <th class="dt-th-sort"
                                    @click="sortBy('sku')"
                                    :class="{ 'is-sorted-asc': sortDirFor('sku') === 'asc', 'is-sorted-desc': sortDirFor('sku') === 'desc' }">
                                    {{ __('products.list.col_sku') }}
                                    <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                                </th>
                                <th class="dt-th-sort"
                                    @click="sortBy('category')"
                                    :class="{ 'is-sorted-asc': sortDirFor('category') === 'asc', 'is-sorted-desc': sortDirFor('category') === 'desc' }">
                                    {{ __('products.list.col_category') }}
                                    <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                                </th>
                                <th class="dt-th-sort"
                                    @click="sortBy('type')"
                                    :class="{ 'is-sorted-asc': sortDirFor('type') === 'asc', 'is-sorted-desc': sortDirFor('type') === 'desc' }">
                                    {{ __('products.list.col_type') }}
                                    <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                                </th>
                                <th class="num dt-th-sort"
                                    @click="sortBy('price')"
                                    :class="{ 'is-sorted-asc': sortDirFor('price') === 'asc', 'is-sorted-desc': sortDirFor('price') === 'desc' }">
                                    {{ __('products.list.col_price') }}
                                    <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                                </th>
                                <th class="num dt-th-sort"
                                    @click="sortBy('cost')"
                                    :class="{ 'is-sorted-asc': sortDirFor('cost') === 'asc', 'is-sorted-desc': sortDirFor('cost') === 'desc' }">
                                    {{ __('products.list.col_cost') }}
                                    <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                                </th>
                                <th class="num dt-th-sort"
                                    @click="sortBy('margin')"
                                    :class="{ 'is-sorted-asc': sortDirFor('margin') === 'asc', 'is-sorted-desc': sortDirFor('margin') === 'desc' }">
                                    {{ __('products.list.col_margin') }}
                                    <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                                </th>
                                <th class="dt-th-sort"
                                    @click="sortBy('active')"
                                    :class="{ 'is-sorted-asc': sortDirFor('active') === 'asc', 'is-sorted-desc': sortDirFor('active') === 'desc' }">
                                    {{ __('products.list.col_status') }}
                                    <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                                </th>
                                <th>{{ __('products.list.col_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody data-dt-rows="table">
                            @if ($view === 'table')
                                @include('admin.products._rows', ['products' => $products])
                            @endif
                        </tbody>
                    </table>
                    </div>
                </x-admin.data-table>
            </div>

            {{-- ── Grid view ───────────────────────────────────────── --}}
            {{-- Grid cards carry the same `data-dt-row` + data-dt-* set as the
                 table rows, so `rowState` rebuilds from either view. Only the
                 active view's rows are ever in the DOM. --}}
            <div x-show="view === 'grid'" @if ($view !== 'grid') x-cloak @endif>
                <div class="prod-grid" data-dt-rows="grid">
                    @if ($view === 'grid')
                        @include('admin.products._cards', ['products' => $products])
                    @endif
                </div>

                {{-- No matches — the grid has no `<x-admin.data-table>` chrome,
                     so it carries its own empty state. --}}
                <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
                    <span class="dt-empty-icon"><x-icon name="search" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('table.empty.no_results_title') }}</div>
                    <div class="dt-empty-sub">{{ __('table.empty.no_results_sub') }}</div>
                </div>

                {{-- Infinite-scroll sentinel: when it nears the viewport the grid
                     fetches and appends the next server page (products-page.js). --}}
                <div x-ref="gridSentinel" class="prod-grid-sentinel" aria-hidden="true"></div>
            </div>
        @endif
    </div>
</x-admin-layout>
