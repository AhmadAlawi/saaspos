<x-admin-layout
    active="products"
    :title="__('products.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('products.crumb_parent')],
        ['label' => __('products.title'), 'href' => route('admin.products.index')],
        ['label' => __('products.actions.new')],
    ]">

    @include('admin.products._form', [
        'mode'              => 'create',
        'product'           => null,
        'categories'        => $categories,
        'brands'            => $brands,
        'units'             => $units,
        'taxGroups'         => $taxGroups,
        'pharmacySchedules' => $pharmacySchedules,
    ])
</x-admin-layout>
