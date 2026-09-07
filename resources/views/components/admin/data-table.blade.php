@props([
    'showSearch'        => true,
    'showPager'         => true,
    'showToolbar'       => true,
    'showSort'          => true,
    'showRefresh'       => true,
    'searchPlaceholder' => null,
])

{{--
    Shared chrome for every list/table screen in the admin.

    Renders the card wrapper + toolbar (with search box) + the row markup
    you provide + the "no matches" empty state + the pager. The component
    is markup-agnostic: pass a `<table>`, a `<ul>`, anything — as long as
    each row carries `data-dt-row`, the `dataTableMixin` running in the
    parent Alpine scope will pick it up.

    The component reads its reactive state straight from the parent
    Alpine scope (`search`, `isEmpty`, `matchedCount`, `pageStart`,
    `pageEnd`, `totalPages`, `page`, `pageSizes`, `isTable`). Any factory
    composed with `composeDataTable(...)` exposes these.

    Slots:
      toolbar-start   left side of the toolbar (typically a title)
      toolbar-info    right side of the toolbar (e.g. a hint or count)
      default         the actual row markup (`<ul>` or `<table>`)
      empty           override the default "No matches" content
      pager-extras    extra controls between page-size and Prev/Next

    Example (Categories list mode):
      <x-admin.data-table>
          <x-slot:toolbar-start>
              <div class="dt-toolbar-title">All categories (10)</div>
          </x-slot:toolbar-start>

          <ul class="cat-list">@include('admin.categories._list', [...])</ul>
      </x-admin.data-table>

    Example (future Products table mode):
      <x-admin.data-table>
          <table class="dt-table">
              <thead>…</thead>
              <tbody>
                  @foreach (…) <tr data-dt-row>…</tr> @endforeach
              </tbody>
          </table>
      </x-admin.data-table>
--}}
<div class="card card-pad-0">
    @if ($showToolbar)
        <div class="dt-toolbar">
            {{ $toolbarStart ?? '' }}

            @if ($showSearch)
                <div class="dt-search">
                    <span class="dt-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                    <input type="search"
                           x-model.debounce.150ms="search"
                           placeholder="{{ $searchPlaceholder ?? __('table.search_placeholder') }}"
                           aria-label="{{ $searchPlaceholder ?? __('table.search_placeholder') }}">
                    <button type="button"
                            class="dt-search-clear"
                            x-show="isFiltered"
                            x-cloak
                            @click="clearSearch()"
                            aria-label="{{ __('table.search_clear') }}">
                        <x-icon name="x" class="w-3.5 h-3.5" />
                    </button>
                </div>
            @endif

            {{ $toolbarInfo ?? '' }}

            @if ($showRefresh || $showSort)
                <x-admin.dt-toolbar-actions :show-sort="$showSort" />
            @endif
        </div>
    @endif

    {{-- Row markup (table / list / tree). The consumer wraps its own
         `<table>` in `.dt-scroll` so wide tables scroll on phones while
         sibling chrome (e.g. an in-card `.inv-filter`) stays full-width. --}}
    {{ $slot }}

    {{-- Empty state — shown when the active search returns zero rows.
         Gated on `dtReady` so it never flashes before the first render
         has counted the rows. Override via the `empty` slot for custom copy. --}}
    <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
        @isset($empty)
            {{ $empty }}
        @else
            <span class="dt-empty-icon"><x-icon name="search" class="w-5 h-5" /></span>
            <div class="dt-empty-title">{{ __('table.empty.no_results_title') }}</div>
            <div class="dt-empty-sub">{{ __('table.empty.no_results_sub') }}</div>
        @endisset
    </div>

    @if ($showPager)
        {{-- Pager — matches design-system/admin/Products.html. Hidden in
             tree mode (host sets `isTable` to false) since paginating a
             hierarchy chops branches mid-way. --}}
        <div class="dt-pager"
             x-show="matchedCount > 0 && isTable !== false"
             x-cloak>
            <div class="dt-pager-info">
                {{-- Custom (non-native) page-size dropdown so the open list
                     matches the system menu styling instead of the browser's
                     native option list. Runs in the host data-table scope. --}}
                <div class="dt-page-size-wrap"
                     @keydown.escape.window="pageSizeOpen = false"
                     @click.outside="pageSizeOpen = false">
                    <button type="button"
                            class="dt-page-size"
                            :class="{ 'is-open': pageSizeOpen }"
                            @click="pageSizeOpen = !pageSizeOpen"
                            :aria-expanded="pageSizeOpen"
                            aria-haspopup="listbox"
                            aria-label="{{ __('table.per_page') }}">
                        <span x-text="pageSize"></span>
                        <span class="dt-page-size-chev" aria-hidden="true"><x-icon name="chevron" class="w-3.5 h-3.5" /></span>
                    </button>
                    <div class="dt-page-size-menu"
                         x-show="pageSizeOpen"
                         x-cloak
                         x-transition.origin.bottom
                         role="listbox">
                        <template x-for="n in pageSizes" :key="n">
                            <button type="button"
                                    class="dt-page-size-opt"
                                    :class="{ 'is-active': pageSize === n }"
                                    role="option"
                                    :aria-selected="pageSize === n"
                                    @click="setPageSize(n)">
                                <span x-text="n"></span>
                                <span class="dt-page-size-check" x-show="pageSize === n" x-cloak><x-icon name="check" class="w-3.5 h-3.5" /></span>
                            </button>
                        </template>
                    </div>
                </div>
                <span>{{ __('table.per_page_suffix') }}</span>
                <span class="dt-pager-sep" aria-hidden="true">·</span>
                {{ __('table.showing') }}
                <span x-text="pageStart"></span>
                {{ __('table.to') }}
                <span x-text="pageEnd"></span>
                {{ __('table.range_of') }}
                <span x-text="matchedCount"></span>
            </div>
            <div class="dt-pager-nav">
                {{ $pagerExtras ?? '' }}
                <button type="button"
                        class="pos-btn pos-btn-xs pos-btn-ghost"
                        :disabled="page === 1"
                        @click="prev()">
                    {{ __('table.prev') }}
                </button>
                {{-- Windowed page numbers (1 … 42 43 44 … 755). Each slot has a
                     stable unique key, so Alpine never recycles a gap element
                     into a page-number one (or vice versa). --}}
                <template x-for="slot in pageWindow" :key="slot.key">
                    <button type="button"
                            class="pos-btn pos-btn-xs"
                            :class="{
                                'pos-btn-ghost': ! slot.gap && page !== slot.page,
                                'is-active':     ! slot.gap && page === slot.page,
                                'is-gap':          slot.gap,
                            }"
                            :disabled="slot.gap"
                            :aria-hidden="slot.gap ? 'true' : null"
                            :aria-current="! slot.gap && page === slot.page ? 'page' : null"
                            @click="slot.gap || gotoPage(slot.page)"
                            x-text="slot.gap ? '…' : slot.page"></button>
                </template>
                <button type="button"
                        class="pos-btn pos-btn-xs pos-btn-ghost"
                        :disabled="page >= totalPages"
                        @click="next()">
                    {{ __('table.next') }}
                </button>
            </div>
        </div>
    @endif
</div>
