<x-admin-layout
    active="roles"
    :title="__('roles.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('roles.crumb_parent')],
        ['label' => __('roles.title')],
    ]">

    {{-- Server-paginated. The <x-admin.data-table> search box + sortable
         headers + paging fetch one page from `admin.roles.rows`. --}}
    <div class="page-wide" x-data="rolesIndexPage({{ \Illuminate\Support\Js::from([
        'endpoint'   => route('admin.roles.rows'),
        'perPage'    => $perPage,
        'total'      => $total,
        'page'       => 1,
        'totalPages' => $totalPages,
    ]) }})">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('roles.title') }}</h1>
                <p class="page-sub">{{ __('roles.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('roles.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.roles.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('roles.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.roles.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('roles.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                <a href="{{ route('admin.roles.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('roles.actions.new') }}
                </a>
            </div>
        </div>

        <x-admin.data-table :search-placeholder="__('roles.list.search_ph')">
            <x-slot:toolbarStart>
                <div class="dt-toolbar-title">
                    {{ __('roles.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                </div>
            </x-slot:toolbarStart>

            <div class="dt-scroll">
            <table class="dt-table">
                <thead>
                    <tr>
                        <th class="dt-th-sort" @click="sortBy('name')"
                            :class="{ 'is-sorted-asc': sortDirFor('name')==='asc', 'is-sorted-desc': sortDirFor('name')==='desc' }">
                            {{ __('roles.columns.role') }}<span class="dt-th-sort-arrow" aria-hidden="true"></span>
                        </th>
                        <th class="num dt-th-sort" @click="sortBy('permissions')"
                            :class="{ 'is-sorted-asc': sortDirFor('permissions')==='asc', 'is-sorted-desc': sortDirFor('permissions')==='desc' }">
                            {{ __('roles.columns.permissions') }}<span class="dt-th-sort-arrow" aria-hidden="true"></span>
                        </th>
                        <th class="num dt-th-sort" @click="sortBy('users')"
                            :class="{ 'is-sorted-asc': sortDirFor('users')==='asc', 'is-sorted-desc': sortDirFor('users')==='desc' }">
                            {{ __('roles.columns.users') }}<span class="dt-th-sort-arrow" aria-hidden="true"></span>
                        </th>
                        <th class="dt-actions-col"><span class="sr-only">{{ __('roles.columns.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody data-dt-rows="table">
                    @include('admin.roles._rows', ['roles' => $roles])
                </tbody>
            </table>
            </div>
        </x-admin.data-table>
    </div>
</x-admin-layout>
