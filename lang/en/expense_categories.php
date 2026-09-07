<?php

return [
    'title' => 'Expense categories',
    'sub'   => 'Buckets your expenses are filed under. Add, rename, or retire them anytime.',
    'new'   => 'New category',

    'list' => [
        'title' => 'Categories',
        'empty' => 'No categories yet',
    ],

    'columns' => [
        'name'    => 'Name',
        'actions' => 'Actions',
    ],

    'editor' => [
        'empty_title' => 'Select a category',
        'empty_sub'   => 'Pick one to edit, or add a new category.',
        'new_title'   => 'New category',
        'edit_title'  => 'Edit category',
    ],

    'fields' => [
        'name'      => 'Name',
        'is_active' => 'Active',
    ],

    'actions' => [
        'toggle_active' => 'Toggle active',
        'delete'        => 'Delete',
        'discard'       => 'Discard',
        'save'          => 'Save changes',
        'saving'        => 'Saving…',
        'create'        => 'Add category',
        'creating'      => 'Adding…',
    ],

    'flash' => [
        'created' => 'Category “:name” added.',
        'updated' => 'Category “:name” updated.',
        'deleted' => 'Category “:name” deleted.',
    ],

    'errors' => [
        'in_use' => '“:name” is used by :count expense(s). Deactivate it instead of deleting.',
    ],
];
