{{--
    Standalone client-side pager — same chrome as the one inside the
    `<x-admin.data-table>` component, but extracted so pages that
    already manage their own card wrapper (e.g. inventory tables with
    a filter form on top) can drop it in without nesting cards.

    Reads its state from the parent Alpine scope. Either the page is
    bound to `dataTable({...})` (the generic Alpine factory) or a
    custom factory composed with `composeDataTable`. Either way it
    exposes: matchedCount, pageStart, pageEnd, totalPages, page,
    pageSize, pageSizes, prev(), next(), gotoPage(), setPageSize().
--}}
<div class="dt-pager" x-show="matchedCount > 0" x-cloak>
    <div class="dt-pager-info">
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
        <span class="tnum" x-text="pageStart"></span>
        {{ __('table.range_sep') }}
        <span class="tnum" x-text="pageEnd"></span>
        {{ __('table.range_of') }}
        <span class="tnum" x-text="matchedCount"></span>
    </div>
    <div class="dt-pager-nav">
        <button type="button"
                class="pos-btn pos-btn-xs pos-btn-ghost"
                :disabled="page === 1"
                @click="prev()">
            {{ __('table.prev') }}
        </button>
        {{-- Windowed page numbers (1 … 42 43 44 … 755). Each slot has a stable
             unique key, so Alpine never recycles a gap element into a
             page-number one (or vice versa). --}}
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
