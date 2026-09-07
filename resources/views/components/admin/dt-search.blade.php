@props([
    'placeholder' => null,
])

{{--
    Standalone search box — same chrome as the one inside
    `<x-admin.data-table>`, but extracted so pages that already manage
    their own card wrapper (master-detail picklists, custom toolbars)
    can drop a search box in without nesting the bundle component.

    Reads its state from the parent Alpine scope — either a page bound
    to `dataTable({...})` or a custom factory composed with
    `composeDataTable`. Either way it exposes `search`, `isFiltered`,
    `clearSearch()`.
--}}
<div class="dt-search">
    <span class="dt-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
    <input type="search"
           x-model.debounce.150ms="search"
           placeholder="{{ $placeholder ?? __('table.search_placeholder') }}"
           aria-label="{{ $placeholder ?? __('table.search_placeholder') }}">
    <button type="button"
            class="dt-search-clear"
            x-show="isFiltered"
            x-cloak
            @click="clearSearch()"
            aria-label="{{ __('table.search_clear') }}">
        <x-icon name="x" class="w-3.5 h-3.5" />
    </button>
</div>
