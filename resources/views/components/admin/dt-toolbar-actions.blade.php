@props(['showSort' => true])

{{--
    Refresh + sort controls that sit at the trailing end of any .dt-toolbar.
    Reads sortCol / sortDir / setSort() / dtRefresh() from the nearest
    parent Alpine scope (any factory composed with composeDataTable).

    Usage in any dt-toolbar:
        <div class="dt-toolbar">
            <div class="dt-toolbar-title">...</div>
            <x-admin.dt-toolbar-actions />
        </div>

    `showSort`: set to false for tables where sort makes no sense (e.g. ordered
    drag-and-drop lists, trees, or pages with server-side sorting).
--}}
<div class="dt-toolbar-actions" x-data="{ dtSortOpen: false }" @click.outside="dtSortOpen = false">
    @if ($showSort)
        <div class="dt-sort-wrap">
            <button type="button"
                    class="pos-btn pos-btn-xs pos-btn-ghost"
                    :class="{ 'is-active': sortCol !== null }"
                    @click="dtSortOpen = !dtSortOpen"
                    :aria-expanded="dtSortOpen"
                    :title="@js(__('table.sort.label'))">
                <x-icon name="sort" class="w-3.5 h-3.5" />
                <span class="dt-sort-label"
                      x-text="sortCol === 'name' && sortDir === 'asc'  ? @js(__('table.sort.name_az'))
                            : sortCol === 'name' && sortDir === 'desc' ? @js(__('table.sort.name_za'))
                            : sortCol === 'id'   && sortDir === 'asc'  ? @js(__('table.sort.id_asc'))
                            : sortCol === 'id'   && sortDir === 'desc' ? @js(__('table.sort.id_desc'))
                            : @js(__('table.sort.label'))">
                </span>
                <span class="inline-flex"
                      :style="dtSortOpen ? 'transform:rotate(180deg);transition:transform 140ms ease' : 'transition:transform 140ms ease'">
                    <x-icon name="chevron" class="w-3 h-3" />
                </span>
            </button>

            <div class="dropdown-panel dt-sort-menu"
                 x-show="dtSortOpen"
                 x-cloak
                 x-transition.origin.top.end>

                <button type="button"
                        class="dropdown-item"
                        :class="{ 'is-active': !sortCol }"
                        @click="setSort(null, null); dtSortOpen = false">
                    <span class="dropdown-item-label">{{ __('table.sort.default') }}</span>
                    <span class="dropdown-item-icon" x-show="!sortCol" x-cloak>
                        <x-icon name="check" class="w-3.5 h-3.5" />
                    </span>
                </button>

                <hr class="dropdown-sep">

                <button type="button"
                        class="dropdown-item"
                        :class="{ 'is-active': sortCol === 'name' && sortDir === 'asc' }"
                        @click="setSort('name', 'asc'); dtSortOpen = false">
                    <span class="dropdown-item-label">{{ __('table.sort.name_az') }}</span>
                    <span class="dropdown-item-icon" x-show="sortCol === 'name' && sortDir === 'asc'" x-cloak>
                        <x-icon name="check" class="w-3.5 h-3.5" />
                    </span>
                </button>

                <button type="button"
                        class="dropdown-item"
                        :class="{ 'is-active': sortCol === 'name' && sortDir === 'desc' }"
                        @click="setSort('name', 'desc'); dtSortOpen = false">
                    <span class="dropdown-item-label">{{ __('table.sort.name_za') }}</span>
                    <span class="dropdown-item-icon" x-show="sortCol === 'name' && sortDir === 'desc'" x-cloak>
                        <x-icon name="check" class="w-3.5 h-3.5" />
                    </span>
                </button>

                <hr class="dropdown-sep">

                <button type="button"
                        class="dropdown-item"
                        :class="{ 'is-active': sortCol === 'id' && sortDir === 'asc' }"
                        @click="setSort('id', 'asc'); dtSortOpen = false">
                    <span class="dropdown-item-label">{{ __('table.sort.id_asc') }}</span>
                    <span class="dropdown-item-icon" x-show="sortCol === 'id' && sortDir === 'asc'" x-cloak>
                        <x-icon name="check" class="w-3.5 h-3.5" />
                    </span>
                </button>

                <button type="button"
                        class="dropdown-item"
                        :class="{ 'is-active': sortCol === 'id' && sortDir === 'desc' }"
                        @click="setSort('id', 'desc'); dtSortOpen = false">
                    <span class="dropdown-item-label">{{ __('table.sort.id_desc') }}</span>
                    <span class="dropdown-item-icon" x-show="sortCol === 'id' && sortDir === 'desc'" x-cloak>
                        <x-icon name="check" class="w-3.5 h-3.5" />
                    </span>
                </button>
            </div>
        </div>
    @endif

    <button type="button"
            class="icon-btn"
            title="{{ __('table.refresh') }}"
            @click="dtRefresh()"
            :disabled="_dtRefreshing">
        <span class="inline-flex" :class="{ 'dt-spin': _dtRefreshing }">
            <x-icon name="refresh" class="w-[15px] h-[15px]" />
        </span>
    </button>
</div>
