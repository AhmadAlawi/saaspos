<x-admin-layout
    active="roles"
    :title="__('roles.create.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('roles.crumb_parent')],
        ['label' => __('roles.title'), 'href' => route('admin.roles.index')],
        ['label' => __('roles.actions.new')],
    ]">

    @include('admin.roles._form', [
        'mode'     => 'create',
        'role'     => null,
        'selected' => [],
        'groups'   => $groups,
    ])
</x-admin-layout>
