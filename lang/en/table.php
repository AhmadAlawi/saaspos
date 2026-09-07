<?php

/**
 * Strings shared by every list/table screen in the admin (search box,
 * pager, empty states). Per-screen titles still live in their own
 * lang/<feature>.php file — only the generic data-table chrome is here.
 */
return [
    'search_label'       => 'Search',
    'search_placeholder' => 'Search…',
    'search_clear'       => 'Clear search',
    'showing'            => 'Showing',
    'to'                 => 'to',
    'range_sep'          => '–',     // between start and end of the visible row range
    'range_of'           => 'of',    // joins "1–25" with "120"
    'per_page'           => 'Rows per page',
    'per_page_suffix'    => '/ page',
    'prev'               => 'Previous',
    'next'               => 'Next',
    'pagination'         => 'Pagination',
    'showing_one'        => 'Showing 1 item',

    'refresh' => 'Refresh',

    // Inline cell popover that reveals a row's detail (see <x-admin.cell-peek>).
    'peek_label' => 'Show details',

    // Per-row "⋮" action menu (see <x-admin.row-actions>).
    'actions'      => 'Actions',
    'actions_open' => 'Open actions menu',
    'action' => [
        'view'   => 'View',
        'edit'   => 'Edit',
        'delete' => 'Delete',
    ],

    'status'   => 'Status',
    'active'   => 'Active',
    'inactive' => 'Inactive',

    'sort' => [
        'label'   => 'Sort',
        'default' => 'Default order',
        'name_az' => 'Name A → Z',
        'name_za' => 'Name Z → A',
        'id_asc'  => 'ID (lowest first)',
        'id_desc' => 'ID (highest first)',
    ],

    'filter_date_from_placeholder' => 'From date',
    'filter_date_to_placeholder'   => 'To date',
    'filter_reset'                 => 'Reset filters',

    'empty' => [
        'no_results_title' => 'No matches',
        'no_results_sub'   => 'Try a different search term.',
        'no_results'       => 'No results.',
    ],
];
