<x-admin-layout
    active="roles"
    :title="__('roles.edit.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('roles.crumb_parent')],
        ['label' => __('roles.title'), 'href' => route('admin.roles.index')],
        ['label' => $role->name],
    ]">

    @include('admin.roles._form', [
        'mode'     => 'edit',
        'role'     => $role,
        'selected' => $selectedIds,
        'groups'   => $groups,
    ])
</x-admin-layout>
