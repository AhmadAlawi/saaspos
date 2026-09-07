<?php

return [
    'title' => 'Price rules',
    'sub'   => 'Scheduled, time-boxed discounts on a product, a category, or everything — separate from a cashier\'s manual per-sale discount.',

    'all_stores' => 'All stores',
    'empty'      => 'No price rules yet.',

    'scope' => [
        'all'      => 'All products',
        'category' => 'Category',
        'product'  => 'Product',
    ],

    'status' => [
        'running'   => 'Running',
        'scheduled' => 'Scheduled',
        'ended'     => 'Ended',
        'disabled'  => 'Disabled',
    ],

    'discount_type' => [
        'pct' => 'Percent off',
        'amt' => 'Amount off',
    ],

    'fields' => [
        'name'                 => 'Name',
        'name_placeholder'     => 'e.g. Shoes 20% off',
        'scope'                => 'Applies to',
        'category'             => 'Category',
        'category_placeholder' => '— choose a category —',
        'product'              => 'Product',
        'product_placeholder'  => 'Search by name, SKU, or barcode…',
        'store'                => 'Store',
        'discount_type'        => 'Discount type',
        'discount_value'       => 'Discount value',
        'starts_at'            => 'Starts',
        'ends_at'              => 'Ends',
        'is_active'            => 'Active',
        'window'               => 'Window',
        'discount'             => 'Discount',
        'status'               => 'Status',
    ],

    'create' => [
        'title' => 'New price rule',
    ],

    'edit' => [
        'title' => 'Edit price rule',
    ],

    'actions' => [
        'create'         => 'New price rule',
        'edit'           => 'Edit',
        'delete'         => 'Delete',
        'save'           => 'Save',
        'cancel'         => 'Cancel',
        'confirm_delete' => 'Delete this price rule?',
    ],

    'flash' => [
        'created' => 'Price rule ":name" created.',
        'updated' => 'Price rule ":name" updated.',
        'deleted' => 'Price rule ":name" deleted.',
    ],

    'errors' => [
        'category_required' => 'Pick a category for a category-scoped rule.',
        'product_required'  => 'Pick a product for a product-scoped rule.',
        'percent_max'       => 'A percent discount can\'t be greater than 100%.',
    ],
];
