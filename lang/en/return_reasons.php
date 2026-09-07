<?php

return [
    'title' => 'Return reasons',
    'sub'   => 'The picklist your cashiers choose from when ringing up a refund. Used in reports to slice "why did we refund?".',
    'new'   => 'New reason',

    'list' => [
        'empty' => 'No return reasons yet.',
        'title' => 'Reasons',
    ],

    'columns' => [
        'code'             => 'Code',
        'name'             => 'Name',
        'sort_order'       => 'Order',
        'default_restock'  => 'Default restock',
        'actions'          => 'Actions',
    ],

    'badges' => [
        'active'           => 'Active',
        'inactive'         => 'Inactive',
        'restock_default'  => 'Restocks by default',
        'no_restock'       => 'Does NOT restock by default',
    ],

    'fields' => [
        'code'             => 'Code',
        'code_help'        => 'A short stable identifier, e.g. `damaged` or `wrong_item`. Used internally; the cashier sees the name.',
        'name'             => 'Name',
        'sort_order'       => 'Sort order',
        'default_restock'  => 'Restock items by default',
        'default_restock_help' => 'When this reason is picked, the refund form\'s restock toggle defaults to on. The cashier can still override per-line.',
        'requires_permission'  => 'Requires manager override',
        'requires_permission_help' => 'Reserved for a future "manager approval" workflow.',
        'is_active'        => 'Active',
    ],

    'editor' => [
        'empty_title' => 'Pick a reason to edit',
        'empty_sub'   => 'Or hit "New reason" to add one.',
        'new_title'   => 'New return reason',
        'new_sub'     => 'Will appear in the refund-form dropdown right away.',
        'edit_title'  => 'Edit reason',
        'edit_id'     => 'ID',
        'edit_updated'=> 'updated',
    ],

    'actions' => [
        'create'         => 'Create reason',
        'creating'       => 'Creating…',
        'save'           => 'Save',
        'saving'         => 'Saving…',
        'discard'        => 'Discard',
        'delete'         => 'Delete reason',
        'delete_confirm' => 'Delete this reason? Past refunds will keep the reason name on their receipts.',
    ],

    'flash' => [
        'created' => 'Reason ":name" created.',
        'updated' => 'Reason ":name" updated.',
        'deleted' => 'Reason ":name" removed.',
    ],

    'errors' => [
        'in_use' => 'Can\'t delete ":name" — it\'s been used on :count refund(s). Toggle it inactive instead.',
    ],
];
