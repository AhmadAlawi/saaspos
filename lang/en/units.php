<?php

return [
    'title'        => 'Units',
    'sub'          => 'Define how products are measured — by piece, by weight, by volume.',
    'crumb_parent' => 'Catalog',

    'actions' => [
        'new'             => 'New unit',
        'save'            => 'Save changes',
        'create'          => 'Create unit',
        'discard'         => 'Discard',
        'delete'          => 'Delete',
        'delete_confirm'  => 'Delete this unit? Any products using it will block the delete.',
        'close'           => 'Close',
        'export'            => 'Export',
        'export_csv'        => 'Download CSV',
        'export_xlsx'       => 'Download Excel (XLSX)',
        'manage_categories' => 'Categories',
    ],

    'list' => [
        'title'        => 'All units',
        'products'     => 'products',
        'base'         => 'Base unit',
        'derived_of'   => '1 :code = :factor :base_code',
        'empty_title'  => 'No units yet',
        'empty_sub'    => 'Click “New unit” to add the first one.',
    ],

    'row' => [
        'toggle_active' => 'Toggle active',
    ],

    'editor' => [
        'empty_title' => 'Select a unit',
        'empty_sub'   => 'Pick a row to view its details, or click “New unit” to add one.',
    ],

    'drawer' => [
        'edit_title'    => 'Edit unit',
        'new_title'     => 'New unit',
        'edit_id'       => 'ID',
        'edit_updated'  => 'updated',
        'new_sub'       => 'Decide how products are counted, weighed, or measured.',
        'code'          => 'Short code',
        'code_placeholder' => 'e.g. kg, l, pc',
        'code_hint'     => 'Up to 16 chars — shown in the product picker.',
        'name'          => 'Display name',
        'name_placeholder' => 'e.g. Kilogram',
        'category'      => 'Measurement category',
        'base_unit'     => 'Base unit',
        'base_unit_none' => '— None (this IS the base)',
        'base_unit_hint' => 'Leave empty if this is a base unit. Otherwise pick the base it converts to.',
        'conversion_factor' => 'Conversion factor',
        'conversion_factor_hint' => 'How many base units one of this equals (e.g. 1 dozen = 12 pc → factor 12).',
        'is_active'     => 'Active (visible in product picker)',
    ],

    'categories' => [
        'count'  => 'Count',
        'weight' => 'Weight',
        'volume' => 'Volume',
        'length' => 'Length',
        'area'   => 'Area',
        'time'   => 'Time',
    ],

    'errors' => [
        'title'             => 'Please review the highlighted fields',
        'base_self'         => 'A unit cannot use itself as the base unit.',
        'has_products'      => 'Cannot delete “:name” — :count product(s) still use this unit. Reassign them first.',
        'has_derived_units' => 'Cannot delete “:name” — :count other unit(s) convert to it. Change their base first.',
    ],

    'actions_extra' => [
        'saving'   => 'Saving…',
        'creating' => 'Creating…',
    ],

    'flash' => [
        'created' => 'Created “:name”.',
        'updated' => 'Updated “:name”.',
        'deleted' => 'Deleted “:name”.',
    ],
];
