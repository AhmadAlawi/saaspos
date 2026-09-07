<x-admin-layout
    active="terminals"
    :title="__('terminals.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('terminals.crumb_parent')],
        ['label' => __('terminals.title')],
    ]">

    {{-- Server-paginated. Only the first page renders inline; search, sort and
         paging fetch one page from `admin.terminals.rows`. Reload-on-mutation:
         delete reloads the current page, toggle reconciles optimistically. --}}
    <div class="page-wide" x-data="terminalsPage({{ \Illuminate\Support\Js::from([
        'endpoint'          => route('admin.terminals.rows'),
        'perPage'           => $perPage,
        'total'             => $total,
        'page'              => 1,
        'totalPages'        => $totalPages,
        'updateUrlTemplate' => route('admin.terminals.update', ['terminal' => '__ID__']),
        'deleteUrlTemplate' => route('admin.terminals.destroy', ['terminal' => '__ID__']),
        'selectUrlTemplate' => route('admin.terminals.select', ['terminal' => '__ID__']),
        'clearSelectionUrl' => route('admin.terminals.clear-selection'),
        'kioskUrl'          => route('kiosk.index'),
        'activeTerminalId'  => $activeTerminalId,
        'rows'              => $rowsPayload,
    ]) }})">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">
                    {{ __('terminals.title') }}
                    @if ($store)
                        <span class="prod-badge prod-badge-muted ms-2">{{ __('terminals.store_badge') }}: {{ $store->name }}</span>
                    @endif
                </h1>
                <p class="page-sub">{{ __('terminals.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('terminals.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.terminals.export', ['format' => 'csv']) }}"
                           class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('terminals.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.terminals.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('terminals.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                <a href="{{ route('admin.terminals.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('terminals.actions.new') }}
                </a>
            </div>
        </div>

        @if ($total === 0)
            <div class="card card-pad-0">
                <div class="term-empty">
                    <span class="term-empty-icon"><x-icon name="pos" class="w-5 h-5" /></span>
                    <div class="term-empty-title">{{ __('terminals.list.empty_title') }}</div>
                    <div class="term-empty-sub">{{ __('terminals.list.empty_sub') }}</div>
                    <a href="{{ route('admin.terminals.create') }}" class="pos-btn pos-btn-sm pos-btn-primary mt-4">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('terminals.actions.new') }}
                    </a>
                </div>
            </div>
        @else
            {{-- ── Search bar (own input — the mixin debounces the fetch, so
                 the component's built-in debounced search would stack). ── --}}
            <div class="inv-filter inv-filter--boxed mb-3">
                <label class="field inv-filter-search">
                    <span class="field-label">{{ __('table.search_label') }}</span>
                    <div class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search"
                               x-model="search"
                               placeholder="{{ __('terminals.list.search_placeholder') }}"
                               class="pos-input">
                    </div>
                </label>
            </div>

            <x-admin.data-table :show-search="false">
                <x-slot:toolbarStart>
                    <div class="dt-toolbar-title">
                        {{ __('terminals.list.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                    </div>
                </x-slot:toolbarStart>

                <ul class="term-list">
                    @include('admin.terminals._list', ['terminals' => $terminals])
                </ul>
            </x-admin.data-table>
        @endif
    </div>
</x-admin-layout>
