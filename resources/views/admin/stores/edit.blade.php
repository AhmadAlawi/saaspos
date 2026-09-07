<x-admin-layout
    active="stores"
    :title="__('stores.edit.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('stores.crumb_parent')],
        ['label' => __('stores.title'), 'href' => route('admin.stores.index')],
        ['label' => $store->name],
    ]">

    @include('admin.stores._form', [
        'mode'  => 'edit',
        'store' => $store,
    ])
</x-admin-layout>
