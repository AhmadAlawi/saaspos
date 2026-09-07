<x-admin-layout
    active="users"
    :title="__('users.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('users.crumb_parent')],
        ['label' => __('users.title')],
    ]">

    {{-- Server-paginated. The <x-admin.data-table> search box + sortable name
         header + paging fetch one page from `admin.users.rows`. --}}
    <div class="page-wide" x-data="usersIndexPage({{ \Illuminate\Support\Js::from([
        'endpoint'   => route('admin.users.rows'),
        'perPage'    => $perPage,
        'total'      => $total,
        'page'       => 1,
        'totalPages' => $totalPages,
    ]) }})">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('users.title') }}</h1>
                <p class="page-sub">{{ __('users.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('users.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.users.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('users.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.users.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('users.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                <a href="{{ route('admin.users.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('users.actions.new') }}
                </a>
            </div>
        </div>

        <x-admin.data-table :search-placeholder="__('users.list.search_ph')">
            <x-slot:toolbarStart>
                <div class="dt-toolbar-title">
                    {{ __('users.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                </div>
            </x-slot:toolbarStart>

            <div class="dt-scroll">
            <table class="dt-table">
                <thead>
                    <tr>
                        <th class="dt-th-sort" @click="sortBy('name')"
                            :class="{ 'is-sorted-asc': sortDirFor('name')==='asc', 'is-sorted-desc': sortDirFor('name')==='desc' }">
                            {{ __('users.columns.user') }}<span class="dt-th-sort-arrow" aria-hidden="true"></span>
                        </th>
                        <th>{{ __('users.columns.access') }}</th>
                        <th>{{ __('users.columns.status') }}</th>
                        <th class="dt-actions-col"><span class="sr-only">{{ __('users.columns.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody data-dt-rows="table">
                    @include('admin.users._rows', ['users' => $users])
                </tbody>
            </table>
            </div>
        </x-admin.data-table>
    </div>
</x-admin-layout>
