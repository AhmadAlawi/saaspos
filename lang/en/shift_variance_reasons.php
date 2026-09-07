<?php

return [
    'title' => 'Cash Mismatch Reasons',
    'sub'   => 'The picklist your cashiers choose from when closing a shift with cash variance. Closed shifts keep the reason label on their Z-reports.',
    'new'   => 'New reason',

    'list' => [
        'title' => 'Reasons',
        'empty' => 'No cash mismatch reasons yet.',
    ],

    'columns' => [
        'code'       => 'Code',
        'name'       => 'Name',
        'sort_order' => 'Sort',
        'actions'    => 'Actions',
    ],

    'badges' => [
        'active'   => 'Active',
        'inactive' => 'Inactive',
    ],

    'editor' => [
        'empty_title' => 'Select a reason',
        'empty_sub'   => 'Pick a row to edit, or create a new one.',
        'edit_title'  => 'Edit reason',
        'edit_id'     => 'ID',
        'edit_updated' => 'updated',
        'new_title'   => 'New reason',
        'new_sub'     => 'Add a new cash mismatch reason to the close-shift picklist.',
    ],

    'fields' => [
        'code'       => 'Code',
        'code_help'  => 'Internal identifier — short, snake_case, used in reports. Stable once shipped.',
        'name'       => 'Display name',
        'sort_order' => 'Sort order',
        'is_active'  => 'Active',
    ],

    'actions' => [
        'discard'  => 'Discard',
        'save'     => 'Save',
        'saving'   => 'Saving…',
        'create'   => 'Create',
        'creating' => 'Creating…',
        'delete'   => 'Delete',
    ],

    'flash' => [
        'created' => ':name added.',
        'updated' => ':name saved.',
        'deleted' => ':name deleted.',
    ],

    'errors' => [
        'in_use' => ':name is used by :count shift(s). Deactivate it instead — past Z-reports keep the label.',
    ],
];
