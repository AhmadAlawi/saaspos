<?php

return [
    'title'        => 'Brands',
    'sub'          => 'Manage the manufacturers and labels behind your products.',
    'crumb_parent' => 'Catalog',

    'actions' => [
        'new'             => 'New brand',
        'save'            => 'Save changes',
        'create'          => 'Create brand',
        'discard'         => 'Discard',
        'delete'          => 'Delete',
        'delete_confirm'  => 'Delete this brand? Any products under it will be moved to the default brand unless you pick another.',
        'close'           => 'Close',
        'export'          => 'Export',
        'export_csv'      => 'Download CSV',
        'export_xlsx'     => 'Download Excel (XLSX)',
    ],

    'list' => [
        'title'        => 'All brands',
        'products'     => 'products',
        'empty_title'  => 'No brands yet',
        'empty_sub'    => 'Click “New brand” to add the first one.',
    ],

    'row' => [
        'toggle_active' => 'Toggle active',
    ],

    'editor' => [
        'empty_title' => 'Select a brand',
        'empty_sub'   => 'Pick a row to view its details, or click “New brand” to add one.',
    ],

    'drawer' => [
        'edit_title'    => 'Edit brand',
        'new_title'     => 'New brand',
        'edit_id'       => 'ID',
        'edit_updated'  => 'updated',
        'new_sub'       => 'Group products by manufacturer or label for cleaner reporting and filtering.',
        'name'          => 'Display name',
        'name_placeholder' => 'e.g. Coca-Cola',
        'logo'          => 'Logo',
        'description'   => 'Description',
        'description_placeholder' => 'Optional notes about this brand',
        'is_active'     => 'Active (visible in product picker)',
        'stats_products' => 'Products',
        'stats_revenue'  => 'Revenue · 30d',
    ],

    'errors' => [
        'title'                 => 'Please review the highlighted fields',
        'default_protected'     => '“:name” is the default brand and cannot be deleted.',
        'replacement_is_self'   => 'Cannot move products into the same brand being deleted.',
        'replacement_not_found' => 'The replacement brand could not be found.',
    ],

    'actions_extra' => [
        'saving'   => 'Saving…',
        'creating' => 'Creating…',
    ],

    'flash' => [
        'created'           => 'Created “:name”.',
        'updated'           => 'Updated “:name”.',
        'deleted'           => 'Deleted “:name”.',
        'deleted_with_move' => 'Deleted “:name” and moved :count product(s) to “:target”.',
    ],

    'delete_dialog' => [
        'title'            => 'Delete “:name”?',
        'no_products'      => 'This brand has no products. Deleting it is safe.',
        'has_products'     => ':count product(s) are linked to this brand. Pick where to move them.',
        'move_label'       => 'Move products to…',
        'move_placeholder' => 'Search brands',
        'default_hint'     => 'Defaults to “:name” if you leave this blank.',
        'confirm'          => 'Delete brand',
        'cancel'           => 'Cancel',
    ],
];
