<x-admin-layout
    active="stores"
    :title="__('stores.create.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('stores.crumb_parent')],
        ['label' => __('stores.title'), 'href' => route('admin.stores.index')],
        ['label' => __('stores.actions.new')],
    ]">

    @include('admin.stores._form', [
        'mode'  => 'create',
        'store' => null,
    ])
</x-admin-layout>
