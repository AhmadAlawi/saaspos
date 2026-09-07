<?php

return [
    'title'        => 'Stores',
    'sub'          => 'The locations your company sells from. Stock, prices, and sales are tracked per store.',
    'crumb_parent' => 'Configure',

    'actions' => [
        'new'        => 'New store',
        'edit'       => 'Edit',
        'save'       => 'Save changes',
        'create'     => 'Create store',
        'delete'     => 'Delete',
        'cancel'     => 'Cancel',
        'activate'      => 'Activate',
        'deactivate'    => 'Deactivate',
        'toggle_active' => 'Toggle active',
        'switch'        => 'Switch',
        'switch_to'     => 'Switch to :name',
        'set_default'   => 'Set as default store',
        'export'        => 'Export',
        'export_csv'    => 'Export CSV',
        'export_xlsx'   => 'Export Excel',
    ],

    'create' => [
        'title' => 'New store',
        'sub'   => 'Add a branch or location to your company.',
    ],
    'edit' => [
        'title' => 'Edit store',
        'sub'   => 'Update this store’s details and configuration.',
    ],

    'form' => [
        'identity'         => 'Identity',
        'identity_sub'     => 'How this store is named and referenced.',
        'address'          => 'Address',
        'address_sub'      => 'Printed on receipts and used in reports.',
        'localization'     => 'Localization',
        'localization_sub' => 'Currency, time zone, and rounding for this store.',
        'behavior'         => 'Behavior',
        'behavior_sub'     => 'How the store handles shifts, tax, and availability.',
    ],

    'fields' => [
        'code'                         => 'Code',
        'code_placeholder'             => 'MAIN',
        'code_help'                    => 'Short, unique. Appears on receipts and sale numbers.',
        'name'                         => 'Name',
        'name_placeholder'             => 'Main Store',
        'phone'                        => 'Phone',
        'email'                        => 'Email',
        'address_line1'                => 'Address line 1',
        'address_line2'                => 'Address line 2',
        'city'                         => 'City',
        'state'                        => 'State / region',
        'postal_code'                  => 'Postal code',
        'country_code'                 => 'Country',
        'country_code_placeholder'     => 'IN',
        'currency'                     => 'Currency',
        'timezone'                     => 'Time zone',
        'locale'                       => 'Locale',
        'locale_placeholder'           => 'en',
        'rounding_mode'                => 'Rounding mode',
        'enforce_shifts'               => 'Enforce shifts',
        'enforce_shifts_help'          => 'Cashiers must open a shift before selling at this store.',
        'require_day_open'             => 'Require the trading day to be opened first',
        'require_day_open_help'        => 'A manager must open today\'s trading day before any cashier can open a shift. Only takes effect when "Enforce shifts" is also on.',
        'discount_threshold'           => 'Discount approval threshold (%)',
        'discount_threshold_help'      => 'Discounts above this percent need the "discounts above threshold" permission (or a manager).',
        'tax_inclusive_pricing'        => 'Tax-inclusive pricing',
        'tax_inclusive_pricing_help'   => 'Prices entered for this store already include tax.',
        'is_active'                    => 'Active',
        'is_active_help'               => 'Inactive stores keep their data but accept no new transactions.',
        'is_default'                   => 'Default store',
        'is_default_help'              => 'The fallback used for product pricing when no store-specific price is set, and where new users start. Only one store can be default.',
        'receipt_template'             => 'Receipt template',
        'receipt_template_default'     => '— Inherit default —',
        'receipt_template_help'        => 'Overrides the default receipt layout for every terminal at this store, unless a terminal has its own override.',
    ],

    'rounding' => [
        'half_up'    => 'Round half up',
        'half_even'  => 'Round half to even (banker’s)',
        'nearest_05' => 'Round to nearest 0.05',
    ],

    'list' => [
        'title'              => 'All stores (:count)',
        'search_placeholder' => 'Search stores…',
        'tip_active_on'      => 'Active — click to deactivate',
        'tip_active_off'     => 'Inactive — click to activate',
        'empty_title'        => 'No stores yet',
        'empty_sub'          => 'Create your first store to start tracking stock and sales.',
        'filter_all'         => 'All',
        'filter_active'      => 'Active',
        'filter_inactive'    => 'Inactive',
    ],

    'columns' => [
        'store'    => 'Store',
        'location' => 'Location',
        'currency' => 'Currency',
        'timezone' => 'Time zone',
        'users'    => 'Users',
        'status'   => 'Status',
        'actions'  => 'Actions',
    ],

    'badges' => [
        'current'  => 'Current',
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'default'  => 'Default',
    ],

    'delete' => [
        'title'   => 'Delete “:name”?',
        'message' => 'The store is soft-deleted. Historical records are preserved. This can’t be undone from here.',
    ],

    'flash' => [
        'created'     => 'Store “:name” created.',
        'updated'     => 'Store “:name” updated.',
        'deleted'     => 'Store “:name” deleted.',
        'activated'   => 'Store “:name” activated.',
        'deactivated' => 'Store “:name” deactivated.',
        'switched'    => 'Switched to “:name”.',
        'default_set' => '“:name” is now the default store.',
    ],

    'errors' => [
        'last_store'         => 'Can’t delete “:name” — your company must keep at least one store.',
        'has_data'           => 'Can’t delete “:name” — it has sales, stock, or other records. Deactivate it instead.',
        'last_active'        => 'Can’t deactivate “:name” — at least one store must stay active.',
        'default_store'      => 'Can’t deactivate “:name” — it’s the default store. Set another store as default first.',
        'has_open_purchases' => 'Can’t deactivate “:name” — it still has draft or in-flight purchases. Close those first.',
    ],

    'switcher' => [
        'label'   => 'Store',
        'none'    => 'No store',
        'heading' => 'Switch store',
        'manage'  => 'Manage stores',
    ],
];
