<?php

return [
    'title'        => 'Roles',
    'sub'          => 'Define what each role can do. Assign roles to users per store.',
    'crumb_parent' => 'Configure',

    'actions' => [
        'new'         => 'New role',
        'edit'        => 'Edit',
        'save'        => 'Save changes',
        'create'      => 'Create role',
        'delete'      => 'Delete',
        'cancel'      => 'Cancel',
        'export'      => 'Export',
        'export_csv'  => 'CSV',
        'export_xlsx' => 'Excel (XLSX)',
    ],

    'create' => ['title' => 'New role', 'sub' => 'Pick the permissions this role grants.'],
    'edit'   => ['title' => 'Edit role', 'sub' => 'Adjust this role’s permissions.'],

    'fields' => [
        'name'         => 'Role name',
        'name_ph'      => 'e.g. Floor Supervisor',
        'description'  => 'Description',
        'description_ph' => 'What is this role for?',
    ],

    'list' => [
        'title'       => 'All roles (:count)',
        'search_ph'   => 'Search roles…',
        'empty_title' => 'No roles yet',
        'empty_sub'   => 'Create a role to start assigning permissions.',
    ],

    'columns' => [
        'role'        => 'Role',
        'permissions' => 'Permissions',
        'users'       => 'Users',
        'actions'     => 'Actions',
    ],

    'badges' => [
        'system' => 'System',
    ],

    'matrix' => [
        'select_all'        => 'Select all',
        'selected_count'    => ':count selected',
        'dangerous'         => 'Dangerous',
        'dangerous_hint'    => 'These permissions can affect billing, security, or data integrity. Grant with care.',
        'group_count'       => ':selected / :total',
    ],

    'delete' => [
        'title'   => 'Delete “:name”?',
        'message' => 'The role is removed permanently. This can’t be undone.',
    ],

    'flash' => [
        'created' => 'Role “:name” created.',
        'updated' => 'Role “:name” updated.',
        'deleted' => 'Role “:name” deleted.',
    ],

    'errors' => [
        'assigned' => 'Can’t delete “:name” — :count user(s) are assigned to this role. Reassign them to another role first.',
    ],
];
