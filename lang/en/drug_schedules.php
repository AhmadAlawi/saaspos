<?php

return [
    'title'        => 'Drug Schedules',
    'sub'          => 'Compliance codes pharmacists print on labels (e.g. India: H · OTC · X, US: Schedule II–V, UK: P · POM).',
    'crumb_parent' => 'Configure',

    'actions' => [
        'new'             => 'New schedule',
        'save'            => 'Save changes',
        'create'          => 'Create schedule',
        'discard'         => 'Discard',
        'delete'          => 'Delete',
        'delete_confirm'  => 'Delete this drug schedule? Any product still tagged with this code will block the delete.',
        'close'           => 'Close',
        'export'          => 'Export',
        'export_csv'      => 'CSV',
        'export_xlsx'     => 'Excel (XLSX)',
    ],

    'list' => [
        'title'        => 'All schedules',
        'empty_title'  => 'No drug schedules yet',
        'empty_sub'    => 'Click “New schedule” to add the first one.',
        'products'     => 'products',
    ],

    'row' => [
        'toggle_active' => 'Toggle active',
    ],

    'editor' => [
        'empty_title' => 'Select a schedule',
        'empty_sub'   => 'Pick a row to view its details, or click “New schedule” to add one.',
    ],

    'drawer' => [
        'edit_title'    => 'Edit schedule',
        'new_title'     => 'New schedule',
        'edit_id'       => 'ID',
        'edit_updated'  => 'updated',
        'new_sub'       => 'Add a regulatory schedule for your market.',
        'code'          => 'Short code',
        'code_placeholder' => 'e.g. H, OTC, II',
        'code_hint'     => 'Up to 16 chars — shown on labels & reports.',
        'name'          => 'Display name',
        'name_placeholder' => 'e.g. Schedule H',
        'description'   => 'Description',
        'description_placeholder' => 'Optional notes about who can prescribe / dispense.',
        'country_code'  => 'Country (ISO-2)',
        'country_code_placeholder' => 'IN, US, UK… (leave empty for global)',
        'is_active'     => 'Active (visible in product picker)',
    ],

    'errors' => [
        'title'         => 'Please review the highlighted fields',
        'has_products'  => 'Cannot delete “:name” — :count product(s) still reference this schedule. Reassign them first.',
    ],

    'delete_dialog' => [
        'title'            => 'Delete “:name”?',
        'no_products'      => 'No products use this schedule. Deleting it is safe.',
        'has_products'     => ':count product(s) are tagged with this schedule. Pick where to move them.',
        'move_label'       => 'Move products to…',
        'move_placeholder' => 'Search schedules',
        'clear_hint'       => 'Leave blank to remove the schedule from these products.',
        'confirm'          => 'Delete schedule',
        'cancel'           => 'Cancel',
    ],

    'actions_extra' => [
        'saving'   => 'Saving…',
        'creating' => 'Creating…',
    ],

    'flash' => [
        'created'            => 'Created “:name”.',
        'updated'            => 'Updated “:name”.',
        'deleted'            => 'Deleted “:name”.',
        'deleted_with_move'  => '“:name” deleted — :count product(s) moved to “:target”.',
        'deleted_with_clear' => '“:name” deleted — schedule cleared from :count product(s).',
    ],
];
