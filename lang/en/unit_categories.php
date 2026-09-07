<?php

return [
    'title' => 'Unit Categories',
    'sub'   => 'Organise your measurement categories (count, weight, volume…).',

    'crumb_parent' => 'Products',

    'list' => [
        'title'       => 'Categories',
        'empty_title' => 'No unit categories yet',
        'empty_sub'   => 'Add a category to group your measurement units.',
    ],

    'editor' => [
        'empty_title' => 'Select a category',
        'empty_sub'   => 'Choose a category from the list to edit it, or create a new one.',
        'edit_title'  => 'Edit category',
        'edit_id'     => 'ID',
        'edit_updated' => 'updated',
        'new_title'   => 'New category',
        'new_sub'     => 'Fill in the details and save.',
    ],

    'fields' => [
        'name'       => 'Name',
        'name_placeholder' => 'e.g. Weight',
        'slug'       => 'Slug',
        'slug_placeholder' => 'e.g. weight',
        'slug_help'  => 'Used internally. Letters, numbers, dashes only. Cannot be changed once units are assigned.',
        'sort_order' => 'Sort Order',
        'is_active'  => 'Active (visible in unit picker)',
    ],

    'actions' => [
        'new'        => 'New Category',
        'save'       => 'Save',
        'create'     => 'Create',
        'discard'    => 'Discard',
        'delete'     => 'Delete',
        'export'     => 'Export',
        'export_csv'  => 'Export CSV',
        'export_xlsx' => 'Export Excel',
        'delete_confirm' => 'This will permanently delete this category. If any units are assigned to it, you must reassign them first.',
    ],

    'actions_extra' => [
        'saving'   => 'Saving…',
        'creating' => 'Creating…',
    ],

    'flash' => [
        'created' => 'Created ":name".',
        'updated' => 'Updated ":name".',
        'deleted' => 'Deleted ":name".',
    ],

    'errors' => [
        'in_use' => 'Cannot delete ":name" — :count unit(s) still use this category.',
    ],

    'badges' => [
        'inactive' => 'Inactive',
    ],
];
