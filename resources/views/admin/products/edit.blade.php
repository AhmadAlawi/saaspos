<x-admin-layout
    active="products"
    :title="__('products.title').' — '.$product->name"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('products.crumb_parent')],
        ['label' => __('products.title'), 'href' => route('admin.products.index')],
        ['label' => $product->name],
    ]">

    @include('admin.products._form', [
        'mode'              => 'edit',
        'product'           => $product,
        'categories'        => $categories,
        'brands'            => $brands,
        'units'             => $units,
        'taxGroups'         => $taxGroups,
        'pharmacySchedules' => $pharmacySchedules,
    ])
</x-admin-layout>
