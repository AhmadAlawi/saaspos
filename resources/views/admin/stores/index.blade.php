<x-admin-layout
    active="stores"
    :title="__('stores.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('stores.crumb_parent')],
        ['label' => __('stores.title')],
    ]">

    <div class="page-wide" x-data="storesPage()">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('stores.title') }}</h1>
                <p class="page-sub">{{ __('stores.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('stores.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.stores.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('stores.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.stores.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('stores.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                <a href="{{ route('admin.stores.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('stores.actions.new') }}
                </a>
            </div>
        </div>

        @if ($stores->isEmpty())
            <div class="card card-pad-0">
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="store" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('stores.list.empty_title') }}</div>
                    <div class="dt-empty-sub">{{ __('stores.list.empty_sub') }}</div>
                </div>
            </div>
        @else
            <x-admin.data-table :show-search="true" :search-placeholder="__('stores.list.search_placeholder')">
                <x-slot:toolbarStart>
                    <div class="dt-toolbar-title">{{ __('stores.list.title', ['count' => $stores->count()]) }}</div>
                </x-slot:toolbarStart>

                <div class="inv-filter inv-filter--ruled">
                    <label class="field inv-filter-select">
                        <span class="field-label">{{ __('stores.columns.status') }}</span>
                        <select class="pos-input"
                                x-data="enhancedSelect()"
                                x-model="activeFilter"
                                x-effect="ts && ts.setValue(activeFilter)">
                            <option value="all">{{ __('stores.list.filter_all') }}</option>
                            <option value="active">{{ __('stores.list.filter_active') }}</option>
                            <option value="inactive">{{ __('stores.list.filter_inactive') }}</option>
                        </select>
                    </label>
                    <button type="button"
                            class="inv-filter-reset"
                            @click="resetFilters()">
                        <x-icon name="x" class="w-3.5 h-3.5" />
                        <span>{{ __('table.filter_reset') }}</span>
                    </button>
                </div>

                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th class="dt-th-sort"
                                @click="sortBy('name')"
                                :class="{ 'is-sorted-asc': sortDirFor('name') === 'asc', 'is-sorted-desc': sortDirFor('name') === 'desc' }">
                                {{ __('stores.columns.store') }}
                                <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                            </th>
                            <th>{{ __('stores.columns.location') }}</th>
                            <th class="num dt-th-sort"
                                @click="sortBy('users')"
                                :class="{ 'is-sorted-asc': sortDirFor('users') === 'asc', 'is-sorted-desc': sortDirFor('users') === 'desc' }">
                                {{ __('stores.columns.users') }}
                                <span class="dt-th-sort-arrow" aria-hidden="true"></span>
                            </th>
                            <th>{{ __('stores.columns.status') }}</th>
                            <th class="dt-actions-col"><span class="sr-only">{{ __('stores.columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stores as $store)
                            @php $isCurrent = $store->id === $activeId; @endphp
                            <tr data-dt-row
                                data-dt-store-id="{{ $store->id }}"
                                data-dt-id="{{ $store->id }}"
                                data-dt-name="{{ $store->name }}"
                                data-dt-location="{{ collect([$store->city, $store->country_code])->filter()->implode(', ') }}"
                                data-dt-users="{{ $store->users_count }}"
                                data-dt-active="{{ $store->is_active ? '1' : '0' }}"
                                class="prod-row"
                                @click="openEdit({{ $store->id }})">
                                <td>
                                    <div class="store-row-id">
                                        <div class="store-row-name-line">
                                            <span class="store-row-name">{{ $store->name }}</span>
                                            @if ($store->is_default)
                                                <span class="store-default-badge">{{ __('stores.badges.default') }}</span>
                                            @endif
                                        </div>
                                        <span class="store-row-code mono">{{ $store->code }}</span>
                                    </div>
                                </td>
                                <td>{{ collect([$store->city, $store->country_code])->filter()->implode(', ') ?: '—' }}</td>
                                <td class="num tnum">{{ $store->users_count }}</td>
                                <td>
                                    @if ($isCurrent)
                                        <template x-if="rowState[{{ $store->id }}]?.is_active">
                                            <span class="badge badge-accent"><span class="badge-dot"></span>{{ __('stores.badges.current') }}</span>
                                        </template>
                                        <template x-if="!rowState[{{ $store->id }}]?.is_active">
                                            <span class="prod-badge prod-badge-muted">{{ __('stores.badges.inactive') }}</span>
                                        </template>
                                    @else
                                        <template x-if="rowState[{{ $store->id }}]?.is_active">
                                            <span class="prod-badge prod-badge-positive">{{ __('stores.badges.active') }}</span>
                                        </template>
                                        <template x-if="!rowState[{{ $store->id }}]?.is_active">
                                            <span class="prod-badge prod-badge-muted">{{ __('stores.badges.inactive') }}</span>
                                        </template>
                                    @endif
                                </td>
                                <td>
                                    <div class="prod-row-actions">
                                        <button type="button"
                                                class="prod-status-btn"
                                                :class="{ 'is-on': rowState[{{ $store->id }}]?.is_active }"
                                                :aria-pressed="rowState[{{ $store->id }}]?.is_active ? 'true' : 'false'"
                                                :title="rowState[{{ $store->id }}]?.is_active ? @js(__('stores.list.tip_active_on')) : @js(__('stores.list.tip_active_off'))"
                                                :disabled="togglingIds.includes({{ $store->id }})"
                                                @click.stop="toggleActive({{ $store->id }})"
                                                aria-label="{{ __('stores.actions.toggle_active') }}">
                                            <span class="prod-status-thumb" aria-hidden="true"></span>
                                        </button>

                                        <x-admin.row-actions>
                                            <x-admin.row-action :href="route('admin.stores.edit', $store)" icon="edit" :label="__('table.action.edit')" />

                                            @unless ($isCurrent)
                                                <form method="POST" action="{{ route('admin.stores.switch', $store) }}"
                                                      @click.stop x-show="rowState[{{ $store->id }}]?.is_active">
                                                    @csrf
                                                    <button type="submit" class="row-action" role="menuitem">
                                                        <span class="row-action-icon"><x-icon name="arrow-right" class="w-4 h-4" /></span>
                                                        <span class="row-action-label">{{ __('stores.actions.switch') }}</span>
                                                    </button>
                                                </form>
                                            @endunless

                                            @unless ($store->is_default)
                                                <form method="POST" action="{{ route('admin.stores.default', $store) }}" @click.stop>
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="row-action" role="menuitem">
                                                        <span class="row-action-icon"><x-icon name="star" class="w-4 h-4" /></span>
                                                        <span class="row-action-label">{{ __('stores.actions.set_default') }}</span>
                                                    </button>
                                                </form>
                                            @endunless

                                            <div class="row-action-sep"></div>
                                            <x-admin.row-action icon="trash" variant="danger" :label="__('stores.actions.delete')"
                                                @click="$store.confirm.show({
                                                    title:        {{ \Illuminate\Support\Js::from(__('stores.delete.title', ['name' => $store->name])) }},
                                                    message:      {{ \Illuminate\Support\Js::from(__('stores.delete.message')) }},
                                                    intent:       'danger',
                                                    confirmLabel: {{ \Illuminate\Support\Js::from(__('stores.actions.delete')) }},
                                                    cancelLabel:  {{ \Illuminate\Support\Js::from(__('stores.actions.cancel')) }},
                                                    onConfirm:    () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.stores.destroy', $store)) }}),
                                                })" />
                                        </x-admin.row-actions>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </x-admin.data-table>
        @endif
    </div>
</x-admin-layout>
