<?php

return [
    'title' => 'Tax',
    'sub'   => 'Atomic components, the groups they bundle into, and which one is the default for new products.',

    'hub' => [
        'components' => [
            'title' => 'Tax components',
            'sub'   => 'Single-line tax rates (e.g. CGST 9%, VAT 20%).',
        ],
        'groups' => [
            'title'        => 'Tax groups',
            'sub'          => 'Bundles of components applied together. What products are tagged with.',
            'default_hint' => 'Default for new products: :name',
        ],
    ],

    'components' => [
        'title'   => 'Tax components',
        'sub'     => 'Atomic taxes. Multiple components can bundle into one group.',
        'new'     => 'New component',

        'badges' => [
            'inactive' => 'Inactive',
        ],

        'list' => [
            'title' => 'All components',
            'empty' => 'No components yet. Create your first to start building tax groups.',
        ],

        'columns' => [
            'code'    => 'Code',
            'name'    => 'Name',
            'rate'    => 'Rate',
            'actions' => 'Actions',
        ],

        'fields' => [
            'code'             => 'Code',
            'code_help'        => 'Programmatic identifier — e.g. IN_CGST_9, UK_VAT_20.',
            'name'             => 'Name',
            'name_placeholder' => 'e.g. Central GST',
            'rate'             => 'Rate',
            'rate_help'        => 'Percent. Use multiple components for compound taxes.',
            'is_active'        => 'Active',
        ],

        'editor' => [
            'empty_title' => 'Select a component',
            'empty_sub'   => 'Pick one from the list, or hit "New component" to add a new one.',
            'edit_title'  => 'Edit component',
            'edit_id'     => 'Editing component',
            'edit_updated'=> 'updated',
            'new_title'   => 'New component',
            'new_sub'     => 'Define a single rate. Groups bundle multiple components together.',
        ],

        'actions' => [
            'save'        => 'Save changes',
            'saving'      => 'Saving…',
            'create'      => 'Create component',
            'creating'    => 'Creating…',
            'discard'     => 'Discard',
            'delete'      => 'Delete',
            'export'      => 'Export',
            'export_csv'  => 'CSV',
            'export_xlsx' => 'Excel (XLSX)',
        ],

        'flash' => [
            'created' => 'Component ":name" created.',
            'updated' => 'Component ":name" saved.',
            'deleted' => 'Component ":name" deleted.',
        ],

        'errors' => [
            'in_use' => '":name" is used by :count group(s). Remove it from those groups first.',
        ],
    ],

    'classifications' => [
        'title' => 'Tax classifications',
        'sub'   => 'Manage the classifications available to tax groups.',

        'crumb_parent' => 'Settings',

        'list' => [
            'title'       => 'All classifications',
            'empty_title' => 'No classifications yet',
            'empty_sub'   => 'Add your first classification to get started.',
        ],

        'editor' => [
            'empty_title' => 'Select a classification',
            'empty_sub'   => 'Pick one from the list, or hit "New classification" to add one.',
            'edit_title'  => 'Edit classification',
            'edit_id'     => 'Editing',
            'edit_updated'=> 'updated',
            'new_title'   => 'New classification',
            'new_sub'     => 'Classifications drive tax filing reports.',
        ],

        'fields' => [
            'name'                    => 'Name',
            'name_placeholder'        => 'e.g. Taxable',
            'slug'                    => 'Slug',
            'slug_placeholder'        => 'e.g. taxable',
            'slug_help'               => 'Lowercase letters, numbers and underscores only. Cannot be changed once in use.',
            'description'             => 'Description',
            'description_placeholder' => 'Optional — shown in the tax groups editor.',
            'sort_order'              => 'Sort order',
            'is_active'               => 'Active',
        ],

        'actions' => [
            'new'            => 'New classification',
            'save'           => 'Save changes',
            'create'         => 'Create classification',
            'discard'        => 'Discard',
            'delete'         => 'Delete',
            'delete_confirm' => 'This will permanently remove the classification.',
            'export'         => 'Export',
            'export_csv'     => 'CSV',
            'export_xlsx'    => 'Excel (XLSX)',
        ],

        'actions_extra' => [
            'saving'   => 'Saving…',
            'creating' => 'Creating…',
        ],

        'flash' => [
            'created' => 'Classification ":name" created.',
            'updated' => 'Classification ":name" saved.',
            'deleted' => 'Classification ":name" deleted.',
        ],

        'errors' => [
            'in_use' => '":name" is used by :count tax group(s). Reassign those groups first.',
        ],
    ],

    'groups' => [
        'title'   => 'Tax groups',
        'sub'     => 'Bundles applied to products. Pick the components participating in each.',
        'new'     => 'New group',

        'list' => [
            'title'          => 'All groups',
            'empty'          => 'No groups yet. Create your first to tag products with.',
            'filter_all'          => 'All',
            'filter_active'       => 'Active',
            'filter_inactive'     => 'Inactive',
            'filter_status_label' => 'Status',
        ],

        'badges' => [
            'default'  => 'Default',
            'inactive' => 'Inactive',
        ],

        'columns' => [
            'code'           => 'Code',
            'name'           => 'Name',
            'classification' => 'Classification',
            'rate'           => 'Rate',
            'usage'          => 'In use',
            'actions'        => 'Actions',
        ],

        'classifications' => [
            'taxable'        => 'Taxable',
            'nil_rated'      => 'Nil-rated',
            'zero_rated'     => 'Zero-rated',
            'exempt'         => 'Exempt',
            'composition'    => 'Composition',
            'reverse_charge' => 'Reverse charge',
        ],

        'fields' => [
            'code'                => 'Code',
            'name'                => 'Name',
            'classification'      => 'Classification',
            'classification_help' => 'Drives filing reports. Use Taxable for ordinary VAT/GST; Exempt/Zero/Nil compute to 0% but are filed differently.',
            'components'                  => 'Components',
            'components_help'             => 'Pick every rate that participates. Selection order is preserved — receipts list them in the order you add them.',
            'components_empty'            => 'No components defined yet. Create some under Tax → Components first.',
            'components_picker_placeholder' => 'Pick one or more components…',
            'rate_total_label'    => 'Combined rate',
            'is_inclusive'        => 'Prices include tax',
            'is_inclusive_help'   => 'On = sticker prices contain tax. Off = tax is added at the till.',
            'is_default'          => 'Use as default for new products',
            'is_default_help'     => 'Only one group can be flagged default — turning this on un-flags any other.',
            'is_active'           => 'Active',
        ],

        'editor' => [
            'empty_title' => 'Select a group',
            'empty_sub'   => 'Pick one from the list, or hit "New group" to add a new one.',
            'edit_title'  => 'Edit group',
            'edit_id'     => 'Editing group',
            'edit_updated'=> 'updated',
            'new_title'   => 'New group',
            'new_sub'     => 'Bundle one or more components. The combined rate is what gets applied.',
        ],

        'actions' => [
            'save'                   => 'Save changes',
            'saving'                 => 'Saving…',
            'create'                 => 'Create group',
            'creating'               => 'Creating…',
            'discard'                => 'Discard',
            'delete'                 => 'Delete',
            'export'                 => 'Export',
            'export_csv'             => 'CSV',
            'export_xlsx'            => 'Excel (XLSX)',
            'manage_classifications' => 'Classifications',
        ],

        'flash' => [
            'created'           => 'Group ":name" created.',
            'updated'           => 'Group ":name" saved.',
            'deleted'           => 'Group ":name" deleted.',
            'deleted_with_move' => 'Group ":name" deleted. :count item(s) moved to ":target".',
        ],

        'errors' => [
            'in_use'             => '":name" is in use by :count item(s). Pick where to move them before deleting.',
            'default_undeletable'=> '":name" is the system default and cannot be deleted.',
        ],

        'delete_dialog' => [
            'title'           => 'Delete ":name"?',
            'has_referrers'   => ':count item(s) are linked to this group. Pick where to move them.',
            'empty_confirm'   => 'No products or categories use this group. Deleting it is safe.',
            'move_label'      => 'Move items to…',
            'move_placeholder'=> 'Search groups',
            'default_hint'    => 'Leave blank to fall back to the default group ":name".',
            'cancel'          => 'Cancel',
            'confirm'         => 'Delete group',
        ],
    ],
];
