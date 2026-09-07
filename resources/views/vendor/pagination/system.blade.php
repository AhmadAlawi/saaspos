{{--
    System pagination view — reuses the .dt-pager chrome from the
    client-side data-table so server-side and client-side lists share
    one look:

      [ 25 / page · Showing 1–25 of 274 ]   ← Prev   1  2  3  …  10   Next →

    Always renders something — even on a single-page result you get
    the page-size dropdown + "Showing N items" caption, so the user
    knows the list isn't truncated and can still change per-page.

    Registered as Laravel's default in AppServiceProvider — every
    `->links()` call across the admin picks it up automatically.

    Per-page sizes match the client-side data-table mixin (see
    composeDataTable defaults): [10, 25, 50, 100].
--}}
@if ($paginator->total() > 0)
<div class="dt-pager">
    <div class="dt-pager-info">
        {{-- Page-size dropdown — opens upward, picks change the URL's
             ?per_page=N param and reload via window.location. Identical
             class set to the client-side data-table dropdown. --}}
        <div class="dt-page-size-wrap"
             x-data="{
                 open: false,
                 current: {{ (int) $paginator->perPage() }},
                 sizes: [10, 25, 50, 100],
                 pick(n) {
                     const url = new URL(window.location.href);
                     url.searchParams.set('per_page', n);
                     url.searchParams.delete('page');
                     window.location.href = url.toString();
                 },
             }"
             @keydown.escape.window="open = false"
             @click.outside="open = false">
            <button type="button"
                    class="dt-page-size"
                    :class="{ 'is-open': open }"
                    @click="open = !open"
                    :aria-expanded="open"
                    aria-haspopup="listbox"
                    aria-label="{{ __('table.per_page') }}">
                <span x-text="current"></span>
                <span class="dt-page-size-chev" aria-hidden="true"><x-icon name="chevron" class="w-3.5 h-3.5" /></span>
            </button>
            <div class="dt-page-size-menu"
                 x-show="open"
                 x-cloak
                 x-transition.origin.bottom
                 role="listbox">
                <template x-for="n in sizes" :key="n">
                    <button type="button"
                            class="dt-page-size-opt"
                            :class="{ 'is-active': current === n }"
                            role="option"
                            :aria-selected="current === n"
                            @click="pick(n)">
                        <span x-text="n"></span>
                        <span class="dt-page-size-check" x-show="current === n" x-cloak><x-icon name="check" class="w-3.5 h-3.5" /></span>
                    </button>
                </template>
            </div>
        </div>
        <span>{{ __('table.per_page_suffix') }}</span>
        <span class="dt-pager-sep" aria-hidden="true">·</span>
        @if ($paginator->total() === 1)
            {{ __('table.showing_one') }}
        @else
            {{ __('table.showing') }}
            <span class="tnum">{{ $paginator->firstItem() }}</span>
            {{ __('table.range_sep') }}
            <span class="tnum">{{ $paginator->lastItem() }}</span>
            {{ __('table.range_of') }}
            <span class="tnum">{{ $paginator->total() }}</span>
        @endif
    </div>

    @if ($paginator->hasPages())
        <nav class="dt-pager-nav" aria-label="{{ __('table.pagination') }}">
            {{-- Prev --}}
            @if ($paginator->onFirstPage())
                <span class="pos-btn pos-btn-xs pos-btn-ghost is-disabled" aria-disabled="true">{{ __('table.prev') }}</span>
            @else
                <a class="pos-btn pos-btn-xs pos-btn-ghost" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('table.prev') }}</a>
            @endif

            {{-- Page numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="dt-pager-dots" aria-hidden="true">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pos-btn pos-btn-xs" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="pos-btn pos-btn-xs pos-btn-ghost" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a class="pos-btn pos-btn-xs pos-btn-ghost" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('table.next') }}</a>
            @else
                <span class="pos-btn pos-btn-xs pos-btn-ghost is-disabled" aria-disabled="true">{{ __('table.next') }}</span>
            @endif
        </nav>
    @endif
</div>
@endif
