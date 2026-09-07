<?php

return [
    'title'        => 'Customers',
    'sub'          => 'The people and businesses you sell to — identity, contact, balances, and loyalty in one place.',
    'crumb_parent' => 'Configure',
    'new'          => 'New customer',

    'filter' => [
        'status_active'   => 'Active',
        'status_inactive' => 'Inactive',
        'status_all'      => 'All',
        'group_all'       => 'All groups',
        'search'          => 'Search name, code, phone, email…',
    ],

    'columns' => [
        'code'        => 'Code',
        'name'        => 'Name',
        'phone'       => 'Phone',
        'email'       => 'Email',
        'group'       => 'Group',
        'outstanding' => 'Outstanding',
        'credit'      => 'Store credit',
        'loyalty'     => 'Loyalty',
        'status'      => 'Status',
        'actions'     => 'Actions',
    ],

    'summary' => [
        'total'        => 'Total customers',
        'with_balance' => 'With balance',
        'outstanding'  => 'Total outstanding',
    ],

    'list' => [
        'title' => 'All customers (:count)',
        'of'    => 'of',    // "12 of 340" — matched count of unfiltered total
    ],

    'badges' => [
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'business' => 'Business',
    ],

    'empty_state' => [
        'title' => 'No customers yet',
        'sub'   => 'Add your first customer to track who\'s buying, what they owe, and what they\'ve earned.',
    ],

    'sections' => [
        'profile'      => 'Profile',
        'profile_sub'  => 'Identity and contact details.',
        'business'     => 'Business',
        'business_sub' => 'Tax and B2B details. Visible when this customer is a business.',
        'pricing'      => 'Pricing & loyalty',
        'pricing_sub'  => 'Group and default discount applied to this customer\'s sales.',
        'credit'       => 'Credit limit',
        'credit_sub'   => 'How much pay-later credit this customer can carry.',
        'addresses'    => 'Addresses',
        'addresses_sub'=> 'One or more addresses — home, office, billing, shipping. Mark one as default.',
        'notes'        => 'Notes',
        'notes_sub'    => 'Internal notes, visible to staff only.',
        'open_sales'      => 'Open sales',
        'open_sales_sub'  => ':count sale(s) with outstanding balance — oldest first. Click any row to record a payment or refund.',
    ],

    'open_sales' => [
        'sale'    => 'Sale',
        'date'    => 'Date',
        'age'     => 'Age',
        'balance' => 'Balance due',
    ],

    'fields' => [
        'code'              => 'Code',
        'code_help'         => 'Leave blank to auto-generate (C-000123).',
        'name'              => 'Name',
        'phone'             => 'Phone',
        'whatsapp_phone'    => 'WhatsApp number',
        'whatsapp_help'     => 'Defaults to the phone number if blank.',
        'whatsapp_opt_out'  => 'Don\'t send WhatsApp messages',
        'email'             => 'Email',
        'dob'               => 'Date of birth',
        'gender'            => 'Gender',
        'is_business'       => 'This is a business customer',
        'business_name'     => 'Business name',
        'gstin'             => 'GSTIN',
        'pan'               => 'PAN',
        'tax_reg'           => 'Tax registration number',
        'group'             => 'Customer group',
        'discount'          => 'Default discount (%)',
        'discount_help'     => 'Applied automatically on this customer\'s sales. Overrides the group default.',
        'credit_limit'      => 'Credit limit',
        'credit_limit_help' => 'Leave 0 (or blank) to block pay-later sales.',
        'notes'             => 'Notes',
        'is_active'         => 'Active',
    ],

    'gender' => [
        'male'              => 'Male',
        'female'            => 'Female',
        'other'             => 'Other',
        'prefer_not_to_say' => 'Prefer not to say',
    ],

    'address' => [
        'label'         => 'Label',
        'label_ph'      => 'Home, Office, Billing…',
        'line1'         => 'Address line 1',
        'line2'         => 'Address line 2',
        'city'          => 'City',
        'state'         => 'State / region',
        'postal'        => 'Postal code',
        'country'       => 'Country',
        'country_ph'    => 'IN',
        'landmark'      => 'Landmark',
        'is_default'    => 'Default',
        'add'           => 'Add address',
        'remove'        => 'Remove address',
    ],

    'kpis' => [
        'outstanding' => 'Outstanding',
        'credit'      => 'Store credit',
        'loyalty'     => 'Loyalty points',
        'since'       => 'Customer since',
        'first_store' => 'First store',
    ],

    'actions' => [
        'export'      => 'Export',
        'export_csv'  => 'CSV',
        'export_xlsx' => 'Excel (XLSX)',
        'save'    => 'Save customer',
        'create'  => 'Create customer',
        'edit'    => 'Edit',
        'view'    => 'View',
        'delete'  => 'Delete',
        'record_payment' => 'Record payment',
        'statement'      => 'View statement',
        'cancel'  => 'Cancel',
        'discard' => 'Discard',
        'back'    => 'Back to customers',
        'toggle_active'   => 'Toggle active',
        'tip_active_on'   => 'Active — click to deactivate',
        'tip_active_off'  => 'Inactive — click to activate',
    ],

    'confirm_delete' => [
        'title'   => 'Delete ":name"?',
        'message' => 'This soft-deletes the customer. History is preserved.',
        'confirm' => 'Delete customer',
    ],

    'flash' => [
        'created'     => 'Customer ":name" created.',
        'updated'     => 'Customer ":name" updated.',
        'deleted'     => 'Customer ":name" deleted.',
        'activated'   => 'Customer ":name" activated.',
        'deactivated' => 'Customer ":name" deactivated.',
    ],

    'errors' => [
        'has_balance' => 'Cannot delete — customer has :kind of :amount. Settle it first, or deactivate instead.',
    ],

    'validation' => [
        'business_name_required' => 'Business name is required for a business customer.',
        'phone_unique'           => 'This phone number is already used by another customer.',
        'whatsapp_unique'        => 'This WhatsApp number is already used by another customer.',
        'email_unique'           => 'This email is already used by another customer.',
    ],

    // Sub-module — customer groups
    'groups' => [
        'title' => 'Customer groups',
        'sub'   => 'Pricing tiers your customers belong to. Default discount applies to every sale.',
        'new'   => 'New group',
        'actions' => [
            'export'      => 'Export',
            'export_csv'  => 'CSV',
            'export_xlsx' => 'Excel (XLSX)',
        ],
        'columns' => [
            'name'     => 'Name',
            'discount' => 'Default discount',
            'status'   => 'Status',
        ],
        'fields' => [
            'name'      => 'Name',
            'discount'  => 'Default discount (%)',
            'is_active' => 'Active',
        ],
        'editor' => [
            'empty_title' => 'Select a group',
            'empty_sub'   => 'Pick a row to edit, or click "New group" to add one.',
            'new_title'   => 'New group',
            'edit_title'  => 'Edit group',
        ],
        'flash' => [
            'created' => 'Group ":name" created.',
            'updated' => 'Group ":name" updated.',
            'deleted' => 'Group ":name" deleted.',
        ],
        'errors' => [
            'in_use' => 'Cannot delete ":name" — customers are still assigned to it. Move them first.',
        ],
        'list_empty' => 'No groups yet. Add one to organise customers by pricing tier.',
        'list_title' => 'All groups',
    ],
];
