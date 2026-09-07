<x-admin-layout
    active="users"
    :title="__('users.edit.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('users.crumb_parent')],
        ['label' => __('users.title'), 'href' => route('admin.users.index')],
        ['label' => $user->name],
    ]">

    @include('admin.users._form', [
        'mode'          => 'edit',
        'user'          => $user,
        'assigned'      => $assigned,
        'stores'        => $stores,
        'roles'         => $roles,
        'canSuperAdmin' => $canSuperAdmin,
    ])
</x-admin-layout>
