<x-admin-layout
    active="users"
    :title="__('users.create.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('users.crumb_parent')],
        ['label' => __('users.title'), 'href' => route('admin.users.index')],
        ['label' => __('users.actions.new')],
    ]">

    @include('admin.users._form', [
        'mode'          => 'create',
        'user'          => null,
        'assigned'      => [],
        'stores'        => $stores,
        'roles'         => $roles,
        'canSuperAdmin' => $canSuperAdmin,
    ])
</x-admin-layout>
